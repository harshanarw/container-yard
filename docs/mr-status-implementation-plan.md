# Container M&R Status — Analysis & Implementation Plan

**Requirement.** Know the current M&R status of any container in the yard at any
time; classify the status by job type; make it filterable in Container Inquiry;
surface it in the other relevant screens and reports.

Companion to [`mr-status-analysis.md`](mr-status-analysis.md), which established
that the data exists but no single status does. This document is the design and
the build order.

---

## Decisions (proposed — see §9 for the two that need a call)

1. **One derivation, two projections.** `ContainerMrStatusService` is the single
   source of truth. Its result is *written* to `containers.mr_status` (current
   state) and to the gate-in row's `gate_movements.mr_status` (that cycle's
   state). Screens read the columns; only the detail view re-derives live.
2. **Materialise, don't derive-on-read.** The previous analysis recommended pure
   read-time derivation. The new requirement — *filterable* in inquiry and
   *reportable* — changes the trade-off: a derived value cannot be a `WHERE`
   clause without a `whereHas` chain per status across four tables, on a
   paginated list. §3.3 argues this is not a repeat of the `containers.status`
   fragility.
3. **Status vocabulary is lane-aware, driven by `yard_job_types` flags.** The
   same stage machinery reads as "Repair in progress" or "Wash in progress"
   depending on the job type and the work order's repair category. This is the
   "classify by job type" part of the requirement.
4. **Holds are a modifier, not a status.** A container can be *under repair* and
   *under customs hold* simultaneously; collapsing them loses information that
   matters at the gate.
5. **A daily reconcile is mandatory, not a safety net.** Some transitions have no
   event to hook — a PTI lapses because a date passed, not because anyone saved a
   record. Without a scheduled recompute the projection is wrong by construction.

---

## 1. Current state

### 1.1 The eight fields that describe M&R today

| Field | Values | Written by |
| --- | --- | --- |
| `containers.status` | available · in_yard · in_repair · reserved · released | `ContainerStatusService`, gate flow |
| `containers.condition` | sound · damaged · require_repair | **gate-in only** |
| `containers.pti_status` / `pti_at` | passed · failed | reefer PTI |
| `inquiries.status` | open · in_progress · estimate_sent · approved · closed | survey flow |
| `inquiries.recommended_action` | repair · monitor · scrap · no_action | survey |
| `estimates.status` | draft · sent · under_review · approved · partially_approved · rejected · returned · completed · cancelled | estimate flow |
| `work_orders.status` | pending · in_progress · on_hold · completed · **rejected** · closed · cancelled | WO flow, QC |
| `container_holds.hold_type` | customs · damage · stop_release · survey_pending · other | `HoldService` |

`work_orders.rejected` (QC failure → rework) was missing from the earlier
analysis. It matters: it is the one state where a container looks finished but is
not, and it must never read as available.

### 1.2 What already exists and is reusable

- **`ContainerStatusService`** — the single point for disposition transitions,
  with `hasOpenWorkOrder()` and the aging stamps. The new service is its sibling,
  not its replacement.
- **`ContainerInquiryService::getContainerHistory()`** — already groups
  inquiries / estimates / work orders / storage / reefer **per gate-in cycle**
  (`withinCycle()`), and already pairs gate-ins to gate-outs via
  `buildGateOutMap()`. The cycle-windowing problem is solved; the derivation
  plugs into it.
- **Observers on every workflow model** — `InquiryObserver`, `EstimateObserver`,
  `WorkOrderObserver`, `ContainerObserver`, `GateMovementObserver` are already
  registered in `AppServiceProvider`. They extend `AuditObserver`, so the
  projection hook goes in a *second*, dedicated observer rather than muddying
  them.
- **`YardJobType` workflow flags** — `survey_applicable`, `estimate_applicable`,
  `repair_applicable`, `wash_applicable`, `reefer_applicable`,
  `storage_applicable`, `cargo_transfer_applicable`, `customs_applicable`.
  This is the classification axis the requirement asks for; it is already
  modelled and already editable as master data.
- **`Container::isReefer()` / `hasValidPti()` / `scopeHeld()` / `activeHire()`**.

### 1.3 The blocker in Container Inquiry

The list paginates **gate-in movements**, not containers — one row per visit,
including closed historical cycles. So a single `containers.mr_status` column
cannot drive that list: a row from a 2024 visit must show what that cycle ended
as, not what the box is doing today. Hence the second projection (§3.2).

---

## 2. The status model

### 2.1 Lanes (job-type classification)

The lane decides which stage vocabulary applies, so a wash job never reads
"awaiting estimate approval" and a storage-only job never reads "awaiting
survey".

| Lane | Selected when | Stage vocabulary |
| --- | --- | --- |
| `repair` | job type `repair_applicable`, or a WO in a repair category | survey → estimate → WO → QC → repaired |
| `wash` | `wash_applicable`, or a WO whose `repair_category` is cleaning/treatment | wash scheduled → washing → washed |
| `reefer` | `reefer_applicable` or `Container::isReefer()` | PTI due → PTI failed → PTI valid |
| `transfer` | `cargo_transfer_applicable` | transfer in progress |
| `storage` | `storage_applicable` and nothing else applicable | in storage |
| `handling` | plain gate/handling job type | in yard, awaiting disposition |

A container can occupy several lanes at once (repair **and** wash). The headline
status comes from the highest-priority active lane —
`repair > wash > reefer > transfer > storage > handling` — and the others render
as secondary chips. Wash and repair share the work-order machinery entirely;
only the label differs, resolved from `repair_categories` via the existing
`RepairCategoryResolver`.

### 2.2 Status catalogue

`group` drives badge colour and the report roll-up.

| Code | Label | Group | Lane |
| --- | --- | --- | --- |
| `awaiting_survey` | Awaiting survey | pending | repair |
| `survey_in_progress` | Survey in progress | in_progress | repair |
| `estimate_pending` | Estimate in preparation | pending | repair |
| `estimate_sent` | Estimate sent — awaiting approval | pending | repair |
| `estimate_rejected` | Estimate rejected | blocked | repair |
| `estimate_approved` | Approved — awaiting work order | pending | repair |
| `repair_scheduled` | Repair scheduled | pending | repair |
| `repair_in_progress` | Repair in progress | in_progress | repair |
| `repair_on_hold` | Repair on hold | blocked | repair |
| `awaiting_qc` | Awaiting QC | pending | repair |
| `qc_failed` | QC failed — rework | blocked | repair |
| `repaired_available` | Repaired — available | ready | repair |
| `wash_scheduled` | Wash scheduled | pending | wash |
| `wash_in_progress` | Washing | in_progress | wash |
| `washed` | Washed — available | ready | wash |
| `pti_due` | PTI due | pending | reefer |
| `pti_failed` | PTI failed | blocked | reefer |
| `condemned` | Condemned / scrap | blocked | — |
| `sound_available` | Sound — available | ready | — |
| `in_storage` | In storage | idle | storage |
| `transfer_in_progress` | Cargo transfer in progress | in_progress | transfer |
| `on_hire` | On hire | committed | — |
| `reserved` | Reserved to booking | committed | — |
| `awaiting_disposition` | In yard — awaiting disposition | idle | handling |
| `gated_out` | Gated out | closed | — |

**Modifiers** (independent of the status, rendered as chips and filterable on
their own): `held` + the hold types; `pti_expired` on a reefer; `overdue` when
the stage age exceeds its threshold.

### 2.3 Resolution order

First match wins, evaluated against **one gate-in cycle's** records. Later
stages override earlier ones, so an open work order beats the survey that
preceded it.

```
 1. cycle has a matched gate-out ............................. gated_out
 2. latest survey recommended_action = scrap ................. condemned
 3. active ContainerHire ..................................... on_hire
 4. work order rejected ...................................... qc_failed
 5. work order on_hold ....................................... repair_on_hold  | wash lane → wash label
 6. work order in_progress ................................... repair_in_progress | wash_in_progress
 7. work order completed ..................................... awaiting_qc
 8. work order pending ....................................... repair_scheduled | wash_scheduled
 9. estimate approved / partially_approved, no live WO ....... estimate_approved
10. latest estimate rejected ................................. estimate_rejected
11. estimate sent / under_review, or inquiry estimate_sent ... estimate_sent
12. estimate draft, or survey recommends repair & no estimate  estimate_pending
13. inquiry open / in_progress ............................... survey_in_progress
14. every WO closed and QC passed ............................ repaired_available | washed
15. survey no_action / monitor, no repair raised ............. sound_available
16. containers.status = reserved ............................. reserved
17. active CargoTransfer ..................................... transfer_in_progress
18. reefer lane, no repair activity, PTI invalid/expired ..... pti_due | pti_failed
19. job type survey_applicable, no inquiry this cycle ........ awaiting_survey
20. job type storage_applicable only ......................... in_storage
21. fallback ................................................. awaiting_disposition
```

PTI sits at 18 deliberately. A reefer mid-repair should read *repair in
progress*, not *PTI due* — but an expired PTI still suppresses export readiness
and still shows as a chip, so nothing is lost by ranking it low.

### 2.4 Export readiness

```
export_ready = group == 'ready'
             AND no active hold
             AND no active hire
             AND containers.status IN (available, in_yard)
             AND (not a reefer OR hasValidPti())
```

`reserved` is deliberately excluded — allocated stock is committed, not free.
The booking screens want *allocatable* stock, which is `export_ready` **or**
`reserved`-to-this-booking; that stays a screen-level concern.

---

## 3. Architecture

### 3.1 `ContainerMrStatusService`

```php
final class ContainerMrStatusService
{
    // Pure resolution over already-loaded records — no queries, fully unit-testable.
    public function resolve(MrStatusContext $ctx): MrStatus;

    // Current state for one container (loads its open cycle).
    public function forContainer(Container $c): MrStatus;

    // Batch — one query per chain for the whole page, never per row.
    public function forGateIns(Collection $gateIns): array;   // keyed by gate_in id

    // Write both projections. Idempotent; returns true when something changed.
    public function refresh(Container $c): bool;

    // The SQL predicate for a status code, so filters and the service agree.
    public function scopeFor(Builder $q, string $code): Builder;
}
```

`MrStatus` is a small readonly value object: `code`, `label`, `group`, `lane`,
`badgeClass`, `since`, `ageDays`, `modifiers[]`, `exportReady`.
`MrStatusContext` carries the cycle's gate-in, gate-out, job type, inquiries,
estimates, work orders, holds, hire and PTI — assembled once by the caller.

Keeping `resolve()` free of queries is what makes the 21-branch order testable
as a table-driven unit test rather than a fixture marathon.

### 3.2 The two projections

| Column | Table | Meaning | Drives |
| --- | --- | --- | --- |
| `mr_status`, `mr_status_group`, `mr_status_at`, `mr_lane`, `export_ready` | `containers` | current state | master list, stock, dashboard, gate-out, booking, reports |
| `mr_status`, `mr_status_group`, `mr_status_at` | `gate_movements` (gate-in rows) | that cycle's state — live if open, terminal if closed | Container Inquiry list, its CSV, movement reports |

For an open cycle the two agree by construction: the open gate-in row *is* the
current cycle. The refresh writes both in one pass.

Putting the projection on `gate_movements` is what makes the inquiry filter a
plain indexed `WHERE` on the table already being paginated — no `whereHas`, no
join, no N+1.

### 3.3 Why materialising is not a repeat of `containers.status`

The `in_repair` stranding happened because *many* controllers wrote the column
directly and one branch (work-order cancel/delete) forgot. The projection here
differs on all three counts:

- **One writer.** Only `refresh()` writes these columns. Nothing else may.
- **A definition to check against.** `resolve()` is authoritative, so drift is
  *detectable*: `containers:reconcile-mr-status` recomputes and diffs. There was
  never a way to ask whether `containers.status` was right.
- **A schedule that self-heals.** The daily reconcile fixes drift without anyone
  noticing it, and is required anyway for the time-driven transitions.

If a hook is ever missed, the failure mode is a stale badge corrected within a
day, not a container permanently unable to leave the yard.

### 3.4 Refresh triggers

| Event | Hook |
| --- | --- |
| Survey saved / status changed | `Inquiry` saved/deleted |
| Estimate status changed | `Estimate` saved/deleted |
| WO created / status / QC | `WorkOrder` saved/deleted |
| Hold placed / cleared | `ContainerHold` saved/deleted |
| Gate-in / gate-out | `GateMovement` saved/deleted |
| Disposition change | `ContainerStatusService::setStatus()` |
| PTI recorded | `ReeferPtiInspection` saved |
| Hire start / end | `ContainerHire` saved |
| Booking allocation / release | `ContainerBookingLine` saved |
| Cargo transfer start / complete | `CargoTransfer` saved |
| **Time passing** (PTI expiry, stage ageing) | **daily reconcile — no event exists** |

All model hooks land in one `MrStatusProjectionObserver` registered against each
model in `AppServiceProvider`, alongside — not inside — the audit observers.

### 3.5 Self-healing read

`ContainerInquiryController::show()` re-derives live and, if it differs from the
stored value, writes the correction before rendering. One container, already
loaded — negligible cost, and it guarantees the screen an operator opens to
check a specific box is never stale.

---

## 4. Schema

```php
// 2024_01_01_000293_add_mr_status_to_containers.php
Schema::table('containers', function (Blueprint $t) {
    $t->string('mr_status', 32)->nullable()->index()->after('status');
    $t->string('mr_status_group', 16)->nullable()->index()->after('mr_status');
    $t->string('mr_lane', 16)->nullable()->after('mr_status_group');
    $t->timestamp('mr_status_at')->nullable()->after('mr_lane');
    $t->boolean('export_ready')->default(false)->index()->after('mr_status_at');
});

// 2024_01_01_000294_add_mr_status_to_gate_movements.php
Schema::table('gate_movements', function (Blueprint $t) {
    $t->string('mr_status', 32)->nullable()->after('condition');
    $t->string('mr_status_group', 16)->nullable()->after('mr_status');
    $t->timestamp('mr_status_at')->nullable()->after('mr_status_group');
    $t->index(['movement_type', 'mr_status']);   // the inquiry list filter
});

// 2024_01_01_000295_backfill_mr_status.php  — chunked, calls refresh()
```

Deliberately `string`, not `enum`: the catalogue will grow, and the codebase
already carries the scar of `ALTER TABLE ... MODIFY COLUMN` migrations to widen
`containers.status`.

`export_ready` is stored rather than computed in the query because it is the
predicate the allocation screens filter on most often, and it depends on four
tables.

---

## 5. Build order

Each phase is independently shippable and independently useful.

### Phase 0 — Fix the misleading Condition column *(do first, standalone)*

The one active defect: `gate_movements.condition` is the arrival snapshot, and
nothing in the repair chain ever writes back to `containers.condition`, so a
repaired container still prints "damaged". Anyone screening for export on that
column today is being misled — worth fixing whether or not the rest ships.

- `WorkOrderController::qc()` — on QC pass with no remaining open WO, set
  `containers.condition = sound` via `ContainerStatusService`.
- Relabel the detail-view field to **Condition on arrival**.
- Backfill command for the historical rows.
- **Files:** `WorkOrderController`, `ContainerStatusService`,
  `container-inquiry/show.blade.php`, `containers/show.blade.php`,
  new `ContainersFixConditionCommand`.

### Phase 1 — The model, service and tests *(no UI)*

- `App\Support\MrStatus` (value object), `App\Support\MrStatusContext`,
  `App\Enums\MrStatusCode` (or a class of constants + label/badge maps, matching
  the `YardJob::statusBadgeClass()` idiom already in use).
- `ContainerMrStatusService` with `resolve()` / `forContainer()` /
  `forGateIns()`.
- Table-driven unit tests over all 21 branches; feature tests for the four
  scenarios that have burned this codebase before (§7).
- **Ships nothing user-visible; everything after depends on it.**

### Phase 2 — Projection, observers, reconcile

- The three migrations from §4.
- `refresh()` + `MrStatusProjectionObserver` + registration.
- `containers:reconcile-mr-status [--fix] [--container=]` — recompute, diff,
  report. Same shape as the existing `FixStrandedRepairStatusCommand` and
  `ReconcileStorageCommand`.
- Schedule it daily. **Note:** the scheduler entry point
  (`bootstrap/app.php` / `Console/Kernel.php`) is not tracked in this repo — this
  one line lands in the deployment repo.

### Phase 3 — Container Inquiry

- **List:** replace **Condition** with **M&R Status** (badge + hold chip). Keep
  arrival condition available via the detail view, relabelled.
- **Filters:** M&R status dropdown (grouped by lane), status-group quick chips,
  "Export ready only" toggle, "On hold only" toggle. All plain `WHERE`s on
  `gate_movements` thanks to §3.2.
- **Detail:** status badge in the header + a progress trail rendering the chain
  stage by stage, driven off the existing per-cycle grouping.
- **CSV export:** add `M&R Status`, `M&R Stage Age (days)`, `Export Ready`.
- **Print view:** badge in the header.
- **Files:** `ContainerInquiryController` (both filter lists),
  `ContainerInquiryService::search()` + `getContainerHistory()`,
  `container-inquiry/{index,show,print}.blade.php`.

### Phase 4 — The other operational screens

| Screen | Change |
| --- | --- |
| `containers/index` | M&R Status column + filter |
| `containers/available-stock` | Export-ready badge; flag rows that are `available` but not export-ready (the reconciliation operators currently do by eye) |
| Dashboard | Replace `pending_repairs` (a raw `in_repair` count) with a group roll-up: pending / in progress / blocked / ready |
| Gate-out releasability (`YardController`) | Include the M&R status in the existing block reason — "in repair" becomes "awaiting QC on WO-00123" |
| Booking allocation (`BookingService`, `ContainerBookingController`) | Filter on `export_ready`; warn when overridden |
| Hire / lessor on-hire / cargo-transfer pickers | Same `export_ready` filter |

### Phase 5 — Reports

- **New: M&R Status Report** (`reports.mr-status`) — containers by status and
  group, with stage ageing buckets, filterable by lane / job type / customer /
  size, CSV export. This is the report that answers "what is in the yard and
  what is it waiting on".
- **Inventory report** — M&R status column, filter, and summary tiles by group
  next to the existing disposition tiles.
- **Daily movements CSV** — M&R status per movement row.
- **Ageing view** — days in current stage, with per-stage thresholds, so
  "awaiting QC for 11 days" is visible without a query.

---

## 6. Performance

- The inquiry list becomes **cheaper**, not dearer: today's Condition column
  needs nothing extra, but a *derived* status would have cost 4–5 extra queries
  per page. The projection makes it one indexed column read.
- `forGateIns()` exists for the paths that must derive live (backfill,
  reconcile): one query per chain for the whole page, never per row.
- `refresh()` is a handful of `EXISTS` queries. It fires on workflow saves, which
  are low-frequency operator actions.
- The backfill and reconcile chunk at 200, matching `ReconcileStorageCommand`.

---

## 7. Tests

Unit (`resolve()`, no DB) — one case per branch of §2.3, plus the orderings that
actually decide behaviour:

- open WO beats a completed survey
- `rejected` WO reads `qc_failed`, **never** `repaired_available`
- container with two WOs, one closed and one open → still in progress
- cancelled/deleted WO → falls back correctly *(the `in_repair` stranding bug)*
- scrap recommendation beats an in-flight estimate
- job type without `survey_applicable` never yields `awaiting_survey`
- wash-category WO yields wash labels, not repair labels

Feature:

- `MrStatusProjectionTest` — each hook in §3.4 updates both projections
- `MrStatusReconcileTest` — deliberate drift is detected and `--fix` repairs it
- `MrStatusInquiryFilterTest` — each filter returns exactly the right rows
- `ExportReadinessTest` — hold, hire, reserved and expired PTI each suppress it
- `MrStatusCycleTest` — a closed historical cycle keeps its terminal status when
  the container starts a new visit

Existing tests to re-run: `tests/Feature/Repair/WorkOrderFlowTest`,
`tests/Feature/Yard/RepairStatusReleaseTest`,
`tests/Feature/Yard/GateOutReleasabilityTest`,
`tests/Feature/Yard/SameDayTurnaroundTest`.

---

## 8. Risks

| Risk | Mitigation |
| --- | --- |
| Projection drifts from reality | One writer, daily reconcile, self-healing detail read, `--fix` command |
| 21-branch order encodes the wrong priorities | The order is the reviewable artefact — §2.3 is written to be argued with before it is coded |
| Historical rows have incomplete chains | Backfill tolerates gaps; missing chain → `awaiting_disposition`, never a wrong-but-confident status |
| Operators read "available" as "exportable" | They are separate columns with separate filters; the stock screen flags the difference explicitly |
| Two status columns (`status` + `mr_status`) confuse | They answer different questions — *where is it* vs *what is it waiting on*. Label them accordingly; §9 asks whether to go further |

---

## 9. Open questions

1. **Should `containers.status` eventually collapse into this?** Its five
   dispositions overlap the catalogue (`in_repair`, `reserved`, `released`). Not
   in scope — too much of the app reads it — but if `mr_status` proves itself,
   `status` could become a derived view of it rather than a parallel truth. Worth
   deciding the direction now so Phase 2 does not paint into a corner.
2. **Stage ageing thresholds.** "Overdue" needs a number per stage (awaiting QC >
   N days, estimate sent > N days). Company setting, job-type setting, or
   hardcoded defaults to start?
3. **Do the 25 codes match how operators actually speak?** The catalogue is
   derived from the schema, not from the yard. Worth one pass with an operator
   before Phase 3 fixes the vocabulary in the UI — renaming after that means
   retraining.

---

## 10. Sequencing summary

| Phase | Delivers | Depends on |
| --- | --- | --- |
| 0 | Condition column stops lying | — |
| 1 | Status model + service + tests | — |
| 2 | Projection, observers, reconcile | 1 |
| 3 | Container Inquiry: column, filters, detail, CSV | 2 |
| 4 | Master list, stock, dashboard, gate-out, booking | 2 |
| 5 | M&R report, inventory report, ageing | 2 |

Phases 0 and 1 are independent and can run in parallel. Phase 3 is the one the
requirement names explicitly; 4 and 5 are independent of each other once 2 lands.
