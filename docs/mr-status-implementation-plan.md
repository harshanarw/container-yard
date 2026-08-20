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
5. **Store the boundary, not a schedule.** ~~A daily reconcile is mandatory.~~
   **Superseded during Phase 2 — see §3.6.** Exactly one transition has no event
   to hook: a reefer's PTI lapses because a date passed. That is handled by
   storing the date the verdict expires and comparing it at read time, which is
   exact at the instant the date rolls over. Stage ageing never needed a job at
   all — it is computed live and was never stored. The reconcile command remains
   as an **audit** tool, not a correctness requirement.

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
 9. estimate approved/partially_approved, no WO has run ...... estimate_approved
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

Rung 9's guard is "no work order has **run**", not "none is **live**". An
earlier draft said the latter, which would have left every repaired container
reading *Approved — awaiting work order* forever, because a closed work order is
not a live one — rung 14 could never be reached with an approved estimate in the
cycle, which is the normal case. Work orders that were all *cancelled* still land
on rung 9: the work was approved and still needs raising.

Rungs 5, 7 and 14 share one stored code across both lanes (`repair_on_hold`,
`awaiting_qc`, `qc_failed`) so that filters and reports stay simple, and the
*label* follows the lane — a wash on hold reads "Wash on hold". Rungs 6, 8 and
14's ready state have distinct codes because the catalogue already gives them
one.

### 2.4 Export readiness

```
export_ready = group == 'ready'
             AND no active hold
             AND no active hire
             AND containers.status IN (available, in_yard)
             AND (not a reefer OR hasValidPti())
```

Stored alongside it is `mr_status_expires_at` — the date this verdict stops
being true on its own, which for a reefer is its PTI's `valid_until` and for
everything else is null. The readable predicate is therefore:

```sql
export_ready = 1 AND (mr_status_expires_at IS NULL OR mr_status_expires_at >= CURDATE())
```

That comparison is the whole reason no nightly job is needed (§3.6).

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
| `mr_status`, `mr_status_group`, `mr_lane`, `mr_status_at`, `export_ready`, `mr_status_expires_at` | `containers` | current state | master list, stock, dashboard, gate-out, booking, reports |
| `mr_status`, `mr_status_group`, `mr_lane`, `mr_status_at` | `gate_movements` (gate-in rows) | that cycle's state — live if open, terminal if closed | Container Inquiry list, its CSV, movement reports |

`mr_lane` on the cycle row was added in Phase 3 (migration 297). It is not
decoration: wash and repair share one stored code for the stages that exist in
both lanes, so the label cannot be derived from the code alone — without it, a
container being washed reads *"Repair on hold"* on the inquiry list, which is
the confusion the lane split exists to prevent.

`export_ready` and `mr_status_expires_at` are deliberately **not** on the cycle
row: they describe the container as it stands now, and a closed 2024 visit has
no meaningful export readiness. The inquiry list's two toggles scope through the
container instead, and are labelled *(now)* to say so.

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
- **A reconcile that can repair it.** `containers:reconcile-mr-status --fix`
  recomputes and corrects, on demand. It is an audit rather than a scheduled
  necessity (§3.6).

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
| Booking allocation / release | *(none needed — see below)* |
| Cargo transfer start / complete | `CargoTransfer` saved |
| **Time passing** (PTI expiry) | **stored boundary, compared at read time (§3.6)** |
| **Time passing** (stage ageing) | **never stored — computed live from `mr_status_at`** |

`ContainerBookingLine` needs no hook: that table has no `container_id` (the link
is `containers.container_booking_line_id`), and `BookingService` saves the
container row itself, so the `Container` hook already covers allocation and
release.

All model hooks land in one `MrStatusProjectionObserver` registered against each
model in `AppServiceProvider`, alongside — not inside — the audit observers.

### 3.5 Self-healing read

`ContainerInquiryController::show()` re-derives live and, if it differs from the
stored value, writes the correction before rendering. One container, already
loaded — negligible cost, and it guarantees the screen an operator opens to
check a specific box is never stale.

### 3.6 Why there is no scheduled job

The original design called for a nightly `containers:reconcile-mr-status --fix`,
justified as "some transitions have no event to hook". Checked against the code,
that justification held for exactly one thing, and even that one has a better
answer.

**What actually depends on the clock.** The resolver has three references to
"now":

| Reference | Feeds | Stored? |
| --- | --- | --- |
| `overdue` threshold | a modifier chip | **No** — modifiers are absent from `toProjection()` |
| `ageDays()` | display | **No** — computed on read |
| PTI `valid_until < today` | `mr_status`, `export_ready` | **Yes** |

So stage ageing never needed a job: modifiers are computed live, and because
`mr_status_at` *is* stored, ageing buckets and "overdue" filters are a `DATEDIFF`
in SQL.

**The one real case, and the fix.** A reefer's PTI lapses because a date passed.
Rather than recompute the world nightly, the resolver records
`mr_status_expires_at` — the date the verdict stops being true — and queries
compare it at read time (§2.4). This is the same shape
`reefer_pti_inspections.valid_until` already uses: a stored boundary, not a flag
someone flips overnight. It is also *more* correct than a job, which would leave
readiness wrong for up to 24 hours between runs.

**The residual, stated plainly.** A reefer idling with a lapsed PTI still
*displays* its pre-lapse status (`sound_available`, say) rather than `pti_due`,
until something touches it. Two things bound that: PTI is rung 18, so it is only
the headline when nothing else is happening; and `Container::scopeStatusExpired`
finds exactly those rows, so a list can overlay a "PTI expired" chip off the same
comparison. The consequential half — *may this box leave?* — is exact.

**The missed-hook argument was weak too.** Writes that bypass model events would
drift silently. There are three in the codebase: two update only **location**
columns (not status triggers), and the third is `ResetTransactions`, a
deliberate whole-yard wipe. So the risk is currently theoretical.

**What the command is for.** An audit: run it after a bulk import, a data fix,
`ResetTransactions`, or a change to the resolution ladder. Weekly scheduling is
reasonable insurance. Daily buys nothing.

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

// 2024_01_01_000297_add_mr_lane_to_gate_movements.php  — Phase 3; see §3.2

// 2024_01_01_000296_add_mr_status_expiry_to_containers.php
Schema::table('containers', function (Blueprint $t) {
    // The date this verdict stops being true on its own — a reefer's PTI
    // valid_until, null for everything else. This is what replaces a nightly
    // recompute (§3.6). Its own migration because 295 backfilled without it,
    // so it re-runs refresh() for reefers.
    $t->date('mr_status_expires_at')->nullable()->index()->after('export_ready');
});
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

- `WorkOrderController::submitQc()` — on QC pass with no remaining open WO, set
  `containers.condition = sound` via `ContainerStatusService`.
- Relabel the two readings of "condition" so they stop competing. The write-back
  makes `containers.condition` **current** state, so it reads *Current
  Condition*; the field that genuinely is the arrival snapshot is the per-cycle
  `gate_movements.condition`, which reads *On arrival*. (An earlier draft of this
  plan said to label the container-level field "Condition on arrival" — that
  would have been made wrong by the write-back in the bullet above.)
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
- **No scheduling required.** An earlier draft made the reconcile a nightly job.
  Checked against the code, only PTI expiry genuinely needed it, and storing the
  boundary (§3.6) handles that exactly — and better than a job, which would
  leave readiness wrong for up to a day between runs. The command stays as an
  audit tool; weekly is reasonable insurance, daily buys nothing.

**Decided:** `containers.status` and `mr_status` stay separate fields
permanently. They answer different questions — *where is it* vs *what is it
waiting on* — and neither is derived from the other.

**Two corrections made while building this phase:**

1. **Gate-out pairing must match `buildGateOutMap()`.** The obvious rule — "the
   first gate-out at or after this gate-in closes the cycle" — mispairs exactly
   the case this yard sees: two visits opened at the same instant, closed out of
   order, where only the shared `yard_job_id` still separates them. It also
   double-uses a single gate-out across two visits. `pairGateOuts()` now mirrors
   `ContainerInquiryService::buildGateOutMap()` precedence (explicit job link
   first, then the time window up to the next gate-in), so the projection and
   the inquiry screen cannot disagree about which visit a status belongs to.
   Pairing needs *all* of a container's movements, not just the ones on the
   current page, or the first and last rows of every page mis-pair.
2. **`container_booking_lines` needs no hook.** §3.4 listed it, but that table
   has no `container_id` — the link is `containers.container_booking_line_id`,
   and `BookingService` saves the container row itself, so the `Container` hook
   already covers allocation and release.

The `Container` hook is narrowed to the attributes that can actually move the
status (`status`, `condition`, `pti_status`, `pti_at`,
`container_booking_line_id`, `reserved_at`), so editing a master field like
notes does not trigger a recompute.

### Phase 3 — Container Inquiry

- **List:** replace **Condition** with **M&R Status** (badge + hold chip). Keep
  arrival condition available via the detail view, relabelled.
- **Filters:** M&R status dropdown (grouped by lane), status-group quick chips,
  "Export ready only" toggle, "On hold only" toggle. All plain `WHERE`s on
  `gate_movements` thanks to §3.2.
- **Detail:** status badge in the header with modifier chips, plus each visit's
  own terminal status on its accordion header.
- **CSV export:** add `M&R Status`, `M&R Stage Age (days)`, `Export Ready`,
  `On Hold`; `Condition` becomes `Condition On Arrival`.
- **Print view:** status in the header and per visit.
- **Files:** `ContainerInquiryController` (both filter lists),
  `ContainerInquiryService::search()`,
  `container-inquiry/{index,show,print}.blade.php`.

**Not built — the stage-by-stage progress trail.** The plan called for one on the
detail view. It was left out because the page already renders the chain: each
visit's accordion lists its inquiries, estimates and work orders in order, and
there is a per-visit event timeline above them. A third rendering of the same
sequence would compete with those rather than add to them. Worth revisiting only
if operators say the existing two are hard to read.

**Correction found while building.** The status filters are indexed columns on
`gate_movements`, but "export ready" and "on hold" describe the container *now*,
not that visit — a closed 2024 cycle has no export readiness. Those two scope
through the container (`whereHas`) and are labelled *(now)* in the UI so the
distinction is visible rather than implied. And `mr_lane` had to be added to the
cycle row (§3.2) or every wash would have read as a repair on the list.

### Phase 4 — The other operational screens

| Screen | Change |
| --- | --- |
| `containers/index` | M&R Status column + filter |
| `containers/available-stock` | Export-ready badge; flag rows that are `available` but not export-ready (the reconciliation operators currently do by eye) |
| Dashboard | Replace `pending_repairs` (a raw `in_repair` count) with a group roll-up: pending / in progress / blocked / ready |
| Gate-out releasability (`YardController`) | Include the M&R status in the existing block reason — "in repair" becomes "awaiting QC on WO-00123" |
| Booking allocation (`BookingService`, `ContainerBookingController`) | **Prefer** `export_ready`, warn when overridden — see below |
| Hire / lessor on-hire pickers | Status shown on each option; **not** filtered |

**The allocation change is a preference, not a filter — deliberately.**

The plan said "filter on `export_ready`". Building it, that turned out to be
unsafe. `export_ready` requires the status to be in the `ready` group, and a
container can sit legitimately at `available` while its chain resolves to
something else — `awaiting_disposition`, for instance, when it was marked
available by hand or arrived on a plain handling job type. Excluding those
outright would have been a silent behaviour change that can leave a booking
unfillable with stock physically sitting in the yard.

So instead:

- `BookingService::autoAllocate` **orders** by export-ready first, then FIFO.
  Ordering can change *which* containers are picked, never *how many*, so it
  cannot break an existing flow.
- The manual picker keeps every candidate selectable, marks the non-ready ones,
  and puts the releasable stock at the top.
- `ContainerBookingController::allocate` raises a `warning` flash naming the
  containers that are not releasable. An operator may have a good reason to hold
  a box against a booking early; the point is that they know, not that they are
  stopped. (Checked that the layout renders `session('warning')` — a warning
  nobody sees would be worse than none.)
- The hire and lessor pickers show the status on each option rather than
  filtering: a box may legitimately go on hire before it is export ready.

Cargo transfer was left alone — its substitute-container flow has its own
guards, and readiness is not the constraint there.

**Gate-out is a wording change only.** The rule is untouched: an `in_repair`
container is still blocked. The message now names the work order and the stage
it is stuck at — *"under repair (WO-00123 — Awaiting QC)"* — because "under
repair" alone tells someone standing at the gate nothing to chase. A container
stranded at `in_repair` with no open work order keeps the old wording, since
there is nothing to name.

**The dashboard keeps `pending_repairs`.** The existing tile is unchanged; the
roll-up is added beside it. Replacing the tile outright would have been a
gratuitous change to a screen people read every morning.

### Phase 5 — Reports

- **New: M&R Status Report** (`reports.mr-status`) — stage roll-up, a per-status
  breakdown with ageing bands (≤7 / 8–14 / 15–30 / >30 days) and an overdue
  count, and a paginated detail list ordered longest-stuck first. Filterable by
  customer / lane / status / stage / size / overdue-only, with CSV export.
- **Inventory report** — M&R status column, stage filter, and a stage roll-up
  beside the existing disposition tiles.
- **Daily movements CSV** — M&R status per movement row.
- **Ageing** — days in the *current stage*, compared against that stage's
  threshold, so "awaiting QC for 11 days" is visible without a query.

**Overdue cannot be one predicate.** Thresholds are per stage, so a status with
a ten-day threshold and one with three are not comparable on days alone: the
query builds an OR-group per configured threshold. An empty threshold map is
guarded explicitly — an empty nested `where` compiles to *no constraint*, which
would report the whole yard as overdue rather than none of it.

**No job-type filter, deliberately.** The plan listed one. Job type is the axis
`mr_lane` is derived from, and the lane is stored on the container, so filtering
by lane asks the same question against an index. Filtering by the job type of a
*particular visit* is a movement-level question, and Container Inquiry already
answers it — it filters by job type and M&R status together. Adding a
container-level job-type filter would have meant either an approximation ("any
visit on this job type") or a correlated subquery for the current cycle; the
first is subtly wrong and the second duplicates a screen that already exists.

**Aggregates, not collections.** The existing reports `->get()` everything and
count in PHP. This one groups in SQL for the summary and breakdown and paginates
the detail, so it stays flat as the yard grows; the CSV chunks for the same
reason — the day someone exports the whole yard is the day the yard is full.

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
| Projection drifts from reality | One writer, self-healing detail read, `--fix` reconcile on demand |
| A stored verdict silently ages out | `mr_status_expires_at` compared at read time — exact, no job (§3.6) |
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
2. ~~**Stage ageing thresholds.**~~ **Decided.** They live in Company Settings as
   a JSON map that merges over `MrStatusCatalogue::AGE_THRESHOLD_DAYS` key by
   key, so tuning one stage keeps the defaults for the rest and a status added
   later works without revisiting the screen. The shipped numbers ship as-is and
   get tuned once real containers start being flagged. `Estimate rejected` (5)
   and `In yard — awaiting disposition` (14) were added — both are genuine
   stalls that had no threshold. The clock is per *stage*, not per visit, and
   the flag is advisory: it never blocks a gate-out or an allocation.
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
