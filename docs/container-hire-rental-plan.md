# Container hire, sub-hire and substitution

A review of what the system already does against the requirement, then a plan.

("§" below is the section sign — "§5" means "section 5 of this document".)

**Headline: roughly 60% of this exists, split across two subsystems that point in
opposite commercial directions and are both called "hire".** The largest single
gap is not a missing feature — it is that one of those subsystems *forbids* the
central case the requirement describes.

---

## 1. The three relationships, and why naming them matters

The requirement describes three distinct commercial positions. Conflating any
two of them is the classic failure in this domain, because each has a different
counterparty, a different ledger side, and a different rate.

| # | Relationship | Yard's role | Ledger | Industry term |
| --- | --- | --- | --- | --- |
| **A** | Yard takes a box from a shipping line for a period | **lessee** | AP — the yard pays | on-hire / lease-in |
| **B** | Yard gives that box to a customer for a period | **lessor** | AR — the yard bills | sub-hire / re-let |
| **C** | Yard swaps cargo into a substitute box | neither | mixed | substitution / stuffing transfer |

**A and B can be live on the same container at the same time**, and that is the
normal case, not an edge case: the yard on-hires from MSC and sub-hires to a
local trader. The margin on that container is (B revenue − A cost), which is
exactly what a per-container P&L should show.

### The naming collision already in the codebase

This is the first thing to fix, because it will cause defects otherwise:

| Code | Means | Direction |
| --- | --- | --- |
| `LessorOnHire` / `lessor_on_hires` | **A** — yard as lessee | AP |
| `ContainerHire` / `container_hires` | **B** — yard as lessor | AR |
| `YardStorage.hire_type = 'on_hire'` | **B** — a hire-period storage row | AR |
| Job type `LESSOR_ONHIRE` | **A** | AP |
| Job types `ONHIRE_IN` / `ONHIRE_OUT` | gate events, either direction | ambiguous |
| Job types `OFFHIRE_IN` / `OFFHIRE_OUT` | gate events, either direction | ambiguous |

Two classes named `…Hire` meaning opposite things, plus four job types that
name neither party. Anyone reading `$container->activeHire` cannot tell from
the name whether the yard is paying or being paid.

---

## 2. What exists today

### A — Lease-in, `LessorOnHire`. Substantially built.

`lessor_on_hires` (migration 000274) carries `yard_job_id`, `lessor_id`,
`gate_movement_id`, `on_hire_date`, `off_hire_date`, `hire_reference`,
`per_diem_rate`, `status`. Each on-hire gets its **own YardJob** under the
`LESSOR_ONHIRE` type, so the period already has a dedicated P&L.

`JobPnlService` already accrues the per-diem daily as WIP cost
(`accruedCost()` / `accruedDays()`), held separate from realised cost until the
supplier invoice lands. That is more than the migration's own comment claims.

**Gaps against the requirement:**

1. **It assumes the box is not yet in the yard.** `LessorOnHireService::onHire()`
   *creates a synthetic gate-in movement* and sets the container `in_yard`. The
   requirement is explicit: *"a container from a Shipping Line which is already
   Gated In"*. Taking a box already on the ground on-hire would produce a second
   arrival in the ledger — and every report built this session reads that ledger.
2. **No storage pause.** Because it creates the arrival, there is no prior
   `YardStorage` to close. Requirement 1.2 wants storage suspended for the hire
   period.
3. **Daily rate only.** Requirement 1.1 wants *"monthly or daily rate or both"*.
4. **No period calculator.** Requirement 1.3 wants: give a date range, get the
   amount, to check against the lessor's invoice.

### B — Sub-hire, `ContainerHire`. Built, but for a different scenario.

`ContainerHireService::onHire()` does the storage accounting properly: it closes
the original customer's `YardStorage` the day before, opens a hire-period row
with `hire_type = 'on_hire'`, and records `original_gate_in_date` so free-day
continuity survives the hire. `billing_gate_in_date` / `effective_gate_in_date`
already exist as the free-day anchor and are respected by storage billing,
`PriorBillingIndex` and `WeeklyRevenueReport`.

**Gaps, and the first is the big one:**

1. **A container on hire cannot be gated out.** In `containerLookup()`:

   ```php
   } elseif ($container->activeHire) {
       $releaseBlock = 'It is currently on hire - complete or cancel the hire before gating out.';
   }
   ```

   The model assumes the box **stays in the yard** during the hire. Requirement
   2.2 says *"the customer will take the container outside the Yard"*. As built,
   that is prohibited. This is the central conflict between the requirement and
   the code, and it drives most of the plan below.

2. **No rate is captured.** The hire storage row is created with
   `'free_days' => 0, 'daily_rate' => 0`. Nothing anywhere in the codebase
   stores a rental rate — `grep` for `rental_rate|monthly_rate|hire_rate`
   returns nothing. So requirement 3 (rates at each stage, feeding customer
   invoices) has **no foundation at all**. This is the largest build item.

3. **No job.** Unlike `LessorOnHire`, a `ContainerHire` has no `yard_job_id`, so
   the sub-hire period has no P&L of its own.

### C — Substitution, `CargoTransfer`. Built, lightly tested.

`CargoTransfer` already knows about hires: `substitute_source` is
`yard_owned` | `on_hired`, and it carries `container_hire_id`. It links source
and substitute gate movements, the substitute's storage row and a reefer plug
session. One test file (`CargoTransferFlowTest`). Your note that it is "not
fully tested" matches what is there.

### Cross-cutting: what is missing everywhere

| Capability | State |
| --- | --- |
| **Sub-jobs** (`parent_job_id` on `yard_jobs`) | **Absent.** `grep` for `parent_job\|sub_job` returns nothing. Requirements 1.5 and 4 both need it. |
| **Rental rates** | **Absent.** No column, no table, no tariff. |
| **Hire party on a gate movement** | **Absent.** `gate_movements` has `customer_id` only. Requirements 5 and 6 need the renting party recorded *on the movement*, not inferred. |
| **Gate-side hire awareness** | **Partial.** `containerLookup()` already returns an `on_hire` block (hire party, date, link) and the gate-out form shows an "On Hire" badge — but only for `ContainerHire`, and only to *block* the release. |
| **Supplier invoice category** | **Partial.** `SupplierInvoiceLine` already has `yard_job_id`, `container_id`, `charge_code_id`, `expense_account_id`. Requirement 1.3's "separate category" is a **charge code**, not a new document type. `ChargeCode` has a `category` column (`storage`, `handling`, …) — a `hire` category is a seeder row, not a schema change. |

---

## 3. Industry practice, and where the requirement matches it

- **Per-diem is the unit.** Container leases bill per container per day, with the
  monthly figure usually a convenience derived from it. Supporting "monthly or
  daily or both" is correct, but store **one canonical rate** plus a basis, and
  derive the other — two independently-entered rates will disagree.
- **The off-hire date is when the box is *accepted*, not when it is returned.**
  A lessor's interchange can sit days behind physical redelivery, and the gap is
  the single most common dispute. The model needs `off_hire_date` (commercial)
  as distinct from the gate movement's timestamp (physical), and they should be
  allowed to differ, with the difference visible.
- **Day counting is contractual.** On-hire day inclusive, off-hire day exclusive
  is common but not universal. It must be a setting, not a constant. Note this
  is a *third* day-count convention alongside `DaysInYard` (elapsed) and storage
  billing (inclusive, net of free days) — it must not reuse either.
- **Hire and storage are mutually exclusive for a given day.** You do not charge
  a customer storage for a day the box was on hire to them. The existing
  storage-splitting in `ContainerHireService` is exactly right and should be the
  template for the lease-in side too.
- **On-hire / off-hire survey (EIR).** Condition is recorded at both ends and
  drives redelivery damage recovery. The `OFFHIRE_IN` job type already sets
  `survey`, `estimate`, `repair` and `damage_capture` true, so the hooks exist.
- **Sub-hire must not outlive the head lease.** If the yard on-hires to 31 March,
  it cannot sub-hire to 15 April. A real constraint worth enforcing.

---

## 4. Impacted modules

Everything below is touched, directly or by consequence.

**Directly modified**

| Module | Why |
| --- | --- |
| `LessorOnHireService` | take-on-hire of a box already in the yard; storage pause; rate capture |
| `ContainerHireService` | allow the box to leave; rate capture; sub-job |
| `YardJob` / `yard_jobs` | `parent_job_id` |
| `YardController::gateIn` / `gateOut` | hire-party capture, release rules, lookup payload |
| `containerLookup()` | must distinguish "blocked" from "on hire, releasing to the hire party" |
| `gate.blade.php` | the hire party at both gates |
| `CargoTransferService` | substitution under a sub-hire |
| `ChargeCode` seeder | `hire` category codes |
| `SupplierInvoice` flow | rental invoice capture against the lease-in job |

**Affected by consequence — must be re-verified**

| Module | Risk |
| --- | --- |
| `StorageBillingController` / `StorageHandlingController` | hire days must not also bill as storage |
| `PriorBillingIndex` | free-day anchor across a hire that now leaves the yard |
| `WeeklyRevenueReport` | new revenue category |
| `JobPnlService` | sub-job roll-up: does a parent show its children's P&L? |
| `ContainerStockAsAt` | **is a box on sub-hire, physically off-site, "in the yard"?** It is not on the ground, but the yard still holds it from the line. Needs an explicit decision. |
| `ContainerVisitDates` / gate search / Inventory | a hire-out gate-out is a real departure and will appear in all of them |
| `ContainerCustodyService` | the visit customer vs the hire party |
| `GateDataCheck` | a box off-site on hire is not a custody defect |
| `ReeferPlugSession` | a reefer that leaves on hire — plug session must close (this is the defect fixed on 15 Sep, in a new guise) |
| `ContainerMrStatusService` | M&R status of an off-site container |

---

## 5. Open questions — these change the design

These need answers before building. They are business decisions, not technical
ones.

1. ~~**When a container is sub-hired and leaves the yard, is it still "in the
   yard" for stock purposes?**~~ **Answered.**

   **It stays on the shipping line's stock, labelled `On-Hired`, and accrues no
   storage until it is off-hired and assigned back to the line.**

   This is the right answer commercially: the box is still the line's, the yard
   still owes it back, and a stock statement that silently drops it would have
   the line chasing containers the yard is holding. Labelling it rather than
   removing it keeps the count honest and says why the storage column is zero.

   Consequences, which are larger than they look:

   - `ContainerStockAsAt` counts a physically absent container. Its rule today
     is *"a gate-in at or before D whose paired gate-out is absent or later
     than D"* — a hire-out departure would fail that, so hire periods need
     excluding from the pairing, not just labelling in the output.
   - Every stock surface needs the label: the As At report, its CSV and
     workbook, Inventory, and the yard list.
   - Storage billing must already be suspended for the period. The existing
     `ContainerHireService` storage split does this; the lease-in side does not
     and must (phase 2).
   - **TEU and occupancy must not count it.** A box off-site occupies no slot,
     so a stock count that includes it and an occupancy figure that includes it
     are two different questions with two different answers.

2. **Can the yard sub-hire a box it does not own and has not leased in?** i.e.
   is B always downstream of A, or can the yard sub-hire a customer's own
   container? The second needs the head customer's consent recorded.

3. **Does the sub-hire rate accrue while the box is off-site only, or from
   on-hire to off-hire regardless of location?** Affects whether the gate
   movements or the hire dates drive billing.

4. **Sub-job numbering.** `JOB-2026-0912` → child as `JOB-2026-0912-01`, or its
   own sequence? The first reads better on an invoice; the second is simpler.

5. **Day-count convention** for hire: on-hire inclusive / off-hire exclusive, or
   both inclusive? Must be settable.

6. **What happens to a sub-hire if the head lease is off-hired early?** Block,
   warn, or cascade?

---

## 6. Plan

Sequenced so each phase is independently deployable and testable, and so the
riskiest decision (§5.1) is forced early rather than discovered late.

**Phase 0 — naming and the sub-job spine.** No behaviour change.
Rename in code (not in the database) so the two directions are unmistakable:
`LessorOnHire` → *lease-in*, `ContainerHire` → *sub-hire*, with docblocks on
both pointing at each other. Add `parent_job_id` to `yard_jobs` with the
relations and a roll-up in `JobPnlService`. This is the foundation for
requirements 1.5 and 4 and it is safe to ship alone.

**Phase 1 — rates.** The missing foundation. A `hire_rates` concept carrying a
canonical per-diem, an optional monthly figure with its derivation basis, a
currency, free days, and effective dates — attached to both the lease-in and the
sub-hire. Nothing bills from it yet. Includes the day-count setting from §5.5.

**Phase 2 — lease-in of a box already in the yard.** `LessorOnHireService` gains
a path that takes an existing in-yard container on hire *without* fabricating a
gate-in: close the current storage, open a lease-in period, open the job. Mirrors
`ContainerHireService`'s storage split, which already does this correctly.

**Phase 3 — the lessor rental calculation and supplier invoice.** Date range in,
amount out, against the captured rate; `hire` charge-code category; the result
tagged to the lease-in job as an AP line. Requirement 1.3.

**Phase 4 — sub-hire that leaves the yard.** The central change, and the one
that needs §5.1 answered first. Replace the blanket `activeHire` release block
with a rule that permits release *to the hire party*, records that party on the
gate movement, and re-admits it at gate-in. Requirements 2.2, 5, 6, 7. Carries
the reefer plug-session close, the stock decision, and re-verification of every
module in §4's second table.

**Phase 5 — sub-hire billing.** Customer invoice from the Phase 1 rate, over the
hire period, on the sub-job. Requirement 3.

**Phase 6 — substitution under hire.** Finish and properly test
`CargoTransferService` for the `on_hired` path. Requirement 2.1.

**Phase 7 — internal hire.** Requirement 2.3. Smallest: a sub-hire with no
counterparty and no invoice, which the existing null-customer handling already
half-supports.

---

## 7. Recommended first step

**Phase 0. Done.** Safe, unblocks requirements 1.5 and 4, and depended on none
of the open questions.

Delivered: `parent_job_id` on `yard_jobs` (migration 000312) with
`parentJob()` / `subJobs()` / `isSubJob()` / `scopeTopLevel()`, and
`JobPnlService::computeWithSubJobs()` returning own / sub-jobs / combined.

The class rename in the original phase 0 was **not** done. `LessorOnHire` and
`ContainerHire` keep their names; `$container->activeHire` has 35 call sites and
renaming it would have been a large, risky diff for a clarity gain that a
docblock delivers at no risk. Instead both models, and that relation, now carry
docblocks stating the direction, the counterparty, the ledger side, and a
pointer to the other class.

**Next: phase 1 (rates), which nothing else can proceed without.** Question 1 is
now answered, so phase 4 is unblocked in principle — but it should still follow
1–3, because releasing a box on hire with no rate captured produces exactly the
silent revenue loss this system already has a history of.
