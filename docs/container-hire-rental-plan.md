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

- ~~**Per-diem is the unit**, with the monthly figure derived from it.~~
  **Corrected.** True of plain per-diem leases, but not of the tiered agreements
  this yard writes. There a monthly rate is a **block price** deliberately
  cheaper than 30 x the daily rate, and deriving one from the other would remove
  the incentive the pricing exists to create. Store both.
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
   both inclusive? Must be settable. *(Partly answered: `hire_month_days`
   settles how long a monthly tier is. Which calendar days count as hire days
   is still open and belongs with phase 2.)*

6. ~~**What happens to a sub-hire if the head lease is off-hired early?**~~
   **Answered: allow.** The lease is open-ended by design — an end date often
   cannot be agreed up front — so a rental running past an *expected* lease end
   is neither blocked nor warned. The lease period is editable instead.

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

**Phase 1 — rates. Done.**

The original sketch here was **wrong and has been replaced.** It proposed "a
canonical per-diem plus a monthly figure derived from it", on the reasoning that
two independently-entered rates will disagree. That reasoning does not survive
contact with how these agreements are actually priced: *a monthly rate is a
block price, not thirty daily rates*. Where 30 days cost 30,000 and day 31
onward costs 1,200, the implied daily rate inside the block is 1,000 — cheaper
on purpose, because that is the incentive to take a longer hire. Deriving either
from the other destroys exactly the structure being priced. **Both are stored;
neither is derived.**

What was built instead — **tiers by elapsed duration**:

- `hire_rate_tiers` (000313), polymorphic across both directions: ordered
  `sequence`, `unit` (monthly | daily), `rate`, and `max_units` where null means
  "everything left". 37 days against [1 month @ 30,000][daily @ 1,200] is
  38,400 — one month plus seven days.
- Duration tiers, **not calendar date ranges**, and that is the point: the
  off-hire date is usually unknown when the agreement is written, and a duration
  tier stays correct whether the box returns on day 20 or day 200.
- Either unit may come first, so "7 days daily, then monthly" is expressible.
  A free introductory period is a tier priced at zero.
- `partial_tier_rule` on each agreement (000314): `daily_fallback` (default),
  `whole_block`, `pro_rata`. The same 20-day hire costs 24,000 / 30,000 / 20,000
  under the three, which is why it is a stored contract term rather than a
  choice made when somebody opens the billing screen — and why a long hire with
  an unknown end can still be billed **unattended**. An override at billing time
  comes with phase 5, recorded: who, when, from what, and why.
- `hire_currency` per agreement; `hire_month_days` as a company setting (30).
- `HireTierPricing` — plain numbers in, plain numbers out, no model or query,
  the same rule `ManualPricing` follows. `HasHireRateTiers` gives both hire
  models `rateTiers()` and `priceFor($days)` from one implementation, so the
  margin is one minus the other rather than the difference between two bugs.

It **never bills silently short**: days falling outside every tier are returned
as `unpriced_days` with a warning, and a daily fallback with no later tier
charges the block and says so. Billing zero quietly is the failure this system
has a history of.

Nothing bills from it yet — phases 3 and 5 do that.

**Phase 2 — lease-in, and re-letting under it.** Larger than first scoped, once
the real structure was described. The tree is **three deep**:

```
Gate In          customer: the shipping line     main job, open for the whole stay
  └─ Lease-In    counterparty: the line (AP)     the yard takes it on hire
       ├─ Rental counterparty: a customer (AR)   re-let, box leaves and returns
       └─ Rental counterparty: a customer (AR)   re-let again, same lease
```

A lease-in can be re-let **many times** over its life, each re-let its own
sub-job. One rule falls out and is worth stating in code: **a job creates gate
movements only when the container physically moves** — the main job on arrival
and final return, each rental when the box leaves and comes back, and the
lease-in *never*, because only commercial custody changes.

*2a — the roll-up recurses. Done.* `computeWithSubJobs()` was one level deep and
its docblock claimed a sub-job of a sub-job was "not a shape this yard has". It
is exactly the shape. The column was self-referential all along, so only the
calculation was wrong. Now walks the tree with a depth bound and a visited set,
because `parent_job_id` admits a cycle and a mis-set parent must not hang the
P&L screen.

*2b — two parties on a job.* The lease-in inverts the usual direction: the
shipping line is still the counterparty, but they **bill the yard**. Three
fields:

| Field | Meaning |
| --- | --- |
| `customer_id` | the counterparty — unchanged |
| `billing_direction` | `ar` (default, every existing job) or `ap` |
| `held_by_customer_id` | who holds the box during this job — the yard's own contact on a lease-in, the renter on a rental |

The direction must be explicit or a job list sums to nonsense. `held_by` is what
makes requirements 5 and 6 answerable: naming the renting party at the gate
cannot be derived from the job type without guessing the moment a new type
appears. The yard gets its own `Customer` contact, tagged internal.

*2c — lease-in of a box already on the ground.* No gate movements in either
direction. Parented to the stay's job. Storage closed at on-hire and reopened at
off-hire, preserving the free-day anchor, mirroring what `ContainerHireService`
already does correctly. `MrStatusContext` taught about `LessorOnHire` so the
label reaches Container Inquiry, the stock reports and the yard list.

*2d — rental sub-jobs.* Repeatable under one lease-in, each with **real** gate
movements, the renting party recorded on both. Absorbs what was Phase 4.

*2d-i — the letting gets a job. Done.* `container_hires.yard_job_id` and
`lessor_on_hire_id` (000317). A `CONTAINER_RELET` sub-job per letting,
receivable from the renter and `held_by` them, parented to the lease or, absent
one, to the stay.

*2d-ii — the gate. Done.* The round trip, both ends:

| | |
| --- | --- |
| **Release** | No longer refused. `HireGateState::releaseBlock()` permits a letting that has a renter and a job, and refuses only an *internal* one — which has no party to release to and no job to book against. The departure carries the **letting's** job, so `movement → job → holder` names the renter with no column of its own, and the purpose is settled as `ONHIRE_OUT` from the rental status rather than asked for. |
| **Return** | A container arriving while a letting is open is the renter bringing it back. The arrival carries the same letting job, the type is `HIRE_RETURN_IN`, no new stay job is opened, **no billable storage row is created** — storage is still suspended and a `normal` row would restart the line's charges mid-lease — and the letting closes. The lease above it stays open, ready to be let again. |
| **Both gates** | `HireGateService` answers both the form and the save from one place, so the warning on screen and the decision on submit cannot drift. The form names the renter, shows the whole job chain (stay → lease → rent), and preselects and locks the purpose. |

Two things had to change underneath it:

- **Job types.** `LESSOR_ONHIRE` and `CONTAINER_RELET` were seeded as `gate_in`
  and so appeared in the gate-in dropdown — inviting an operator to record the
  arrival that migration 000316 exists to prevent. A third `movement_direction`,
  `commercial`, marks a job type that is never a gate purpose. `HIRE_RETURN_IN`
  is new: the renter's return had no purpose to arrive on, and requirement 4
  asks for one to be auto-selected.
- **Visit pairing.** A rental round trip puts three movements on one container
  and the job link alone mis-pairs them: the return carries the same job as the
  departure it is returning from, so it paired with a gate-out that had already
  happened, while the stay looked as though the box never left. The rule now
  lives once, in `App\Support\VisitPairing` — Container Inquiry and
  `ContainerMrStatusService` had a copy each, with comments promising they
  matched — and runs in three passes, in descending order of trust:

  1. **the job link, inside the visit** — the strongest evidence there is, and
     the only thing that separates two visits opened at the same instant and
     closed out of order;
  2. **the clock, inside the visit** — what the link could not place, including
     departures carrying a job of their own. This is where the rental departure
     closes the stay it actually ended; before, a departure with an unmatched
     job was excluded from the fallback and simply discarded;
  3. **the job link, however the dates fall** — a departure recorded *before*
     its arrival is a data-entry error, not a visit, and Gate Data Check can
     only report it as `out_before_in` if the two are paired at all. Running it
     last means a well-formed visit is never given up to a broken one.

  The SQL mirror in `ContainerInquiryService::whereDeparture()` reproduces the
  first two passes and deliberately not the third, which turns on a departure
  being left over once the real visits have taken theirs — something a
  correlated subquery cannot know.

And one in the status ladder: the commitment rung moved **above** the
closed-cycle rung. A rented box physically leaves, so its cycle closes — but the
yard is still paying the line rent on it, so reading "Gated out" would drop the
container off its owner's statement mid-hire. `On hire — rented out` now
survives the departure, which is requirement 5: on-hire status and
inside/outside-yard status answered separately, at any moment.

*2d-iii — the stock readers. Done.* The one case 2d-ii created: a container
that is `released` and leased at once.

**Inventory** needed nothing. It has no status filter, so a rented-out box was
still listed, and the ladder change above already labels it.

**Container Stock (As At)** dropped it. Its rule — *in the yard at D if it has
an arrival at or before D whose paired departure is absent or later* — is right
about the ground and wrong about the books: the rental departure is real and
correctly recorded, so the box fell off the report while the yard was paying
rent on it. It now carries a `custody` beside `stage`, answering the two
questions separately:

| | `stage` (where it stands) | `custody` (whose it is) |
| --- | --- | --- |
| ordinary | In Yard | In yard |
| leased in | In Yard | On hire |
| leased in + let out | Released | On hire - rented out |

An agreement running at the cutoff keeps the container on stock, on the
**stay's** arrival — so the days count from when the box actually got here, not
from the rental. The yard slot is blanked when it is not on the ground, because
a slot the box is not standing in sends somebody to look for it. Custody is
selected on **dates, not `status`**: a lease closed in November was running in
September, and reading the live status would rewrite last month's stock every
time an agreement ended. The summary now states `on_ground` and `rented_out`
separately, so the headline count is not read as a yard census.

**Stock states — three, not two:** `In Yard`, `On Hire`, `On Hire — Rented Out`.
The line keeps seeing their box, sees the yard holds it on hire, and sees when
it is physically away with a renter.

**The lease is open-ended.** `off_hire_date` stays nullable and an *expected* end
date is separate and editable. A rental running past the expected lease end is
**allowed** — not blocked, not warned. This reverses the earlier note in §3 that
a sub-hire must not outlive the head lease: sound for a fixed-term lease, wrong
for an open one.

**Phase 3 — the lessor rental calculation and supplier invoice.** Date range in,
amount out, against the captured rate; `hire` charge-code category; the result
tagged to the lease-in job as an AP line. Requirement 1.3.

**Phase 4 — sub-hire that leaves the yard. Absorbed into 2d-ii and 2d-iii,
done.**

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
