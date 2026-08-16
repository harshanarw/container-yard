# M&R status visibility — analysis & gaps

Question: can the current M&R state of a container (surveyed, estimate sent,
arrived damaged, repair in progress, repaired/available…) be shown in Container
Inquiry, so operators can pick containers ready for export?

Short answer: **the data is all there, but no single status exists, and the one
condition indicator the screen does show is actively misleading.**

## 1. Where M&R state currently lives

Eight fields across four tables, each describing one slice.

| Field | Values | Written by | Actually means |
| --- | --- | --- | --- |
| `containers.condition` | sound · damaged · require_repair | **gate-in only** | condition **on arrival** |
| `containers.status` | available · in_yard · in_repair · reserved · released | gate flow, survey, WO QC pass | physical / commercial disposition |
| `inquiries.status` | open · in_progress · estimate_sent · approved · closed | survey flow | survey progress |
| `inquiries.overall_condition` | excellent · good · fair · poor · condemned | survey | assessed condition |
| `inquiries.recommended_action` | repair · monitor · scrap · no_action | survey | survey outcome |
| `estimates.status` | draft · sent · under_review · approved · partially_approved · rejected · returned · completed · cancelled | estimate flow | quote progress |
| `work_orders.status` | pending · in_progress · on_hold · completed · closed · cancelled | WO flow | repair progress |
| `container_holds.hold_type` | customs · damage · stop_release · survey_pending · other | holds | release blocker |

The chain is well modelled and correctly linked
(`inquiry → estimate → work_order → repair_invoice`, all carrying
`container_id` / `container_no`). Nothing needs re-plumbing.

## 2. What Container Inquiry shows today

**List view** — Container No · Customer · Job No · Job Type · Gate In · Gate Out ·
Job Status · Condition · Size

- **Job Status** is `yard_jobs.status` (open / closed) — the operational gate job,
  not anything about repair.
- **Condition** is `gate_movements.condition` — the arrival snapshot.

**Detail view** — shows `containers.status` and `containers.condition` in the
header, and `getContainerHistory()` already assembles inquiries, estimates, work
orders and repair invoices grouped per gate-in cycle. The raw material for a
status is present; it is simply presented as four separate lists for the reader
to interpret.

## 3. Gaps

### Gap 1 — the Condition column is the arrival snapshot (critical)

`gate_movements.condition` is frozen at gate-in. Separately, **nothing in the
survey → estimate → work order → QC chain ever writes back to
`containers.condition`** — verified across `SurveyController`,
`InquiryController` and `WorkOrderController`; the only writers are the gate-in
and movement-edit paths in `YardController`.

So a container that arrived damaged, was repaired and passed QC still reads
**damaged** in both the list and the detail. For export selection that is exactly
inverted: the containers most likely to be repaired and ready are the ones
flagged damaged.

### Gap 2 — no single M&R status (critical)

Answering "where is this container in the repair cycle?" means opening the detail
view and reading four lists. There is no derived status anywhere in the codebase
(searched: `mr_status`, `repair_status`, `derivedStatus` — no matches).

### Gap 3 — `containers.status` is stored, not derived (high)

It is a plain column maintained by scattered writes across three controllers.
This has already failed in production: cancelling or deleting a work order left
containers stranded at `in_repair` with nothing left to close, blocking gate-out
(fixed, plus `containers:fix-repair-status` to clear the backlog). Any derived
status that trusts this column inherits that fragility.

### Gap 4 — no export-readiness concept (high)

No field, flag or filter anywhere expresses "ready for export". This is the
decision the request is actually about.

### Gap 5 — the list cannot filter by M&R state (medium)

The only status filter on the inquiry search targets `yard_jobs.status`.

### Gap 6 — `in_repair` is ambiguous (medium)

A survey with `recommended_action = repair` sets `in_repair` **before any work
order exists**. So `in_repair` means either "damaged, awaiting a work order" or
"actively being repaired" — indistinguishable from the column alone.

### Gap 7 — no ageing on the chain (low)

"How long has this been awaiting QC?" needs a join and a derivation; nothing
surfaces dwell time per M&R stage.

## 4. Proposal — derive, don't store

Add a `ContainerMrStatusService` that resolves one status at read time from the
chain. Deliberately **not** a new stored column: Gap 3 is the demonstration of
what a stored status does when a workflow branch forgets to update it.

Resolution order (first match wins — later stages override earlier ones, so a
container with an open work order reads as under repair regardless of its survey):

| # | Status | Condition |
| --- | --- | --- |
| 1 | **Gated out** | `containers.status = released` |
| 2 | **On hold** | an active `container_holds` row (shown as a modifier on any status below) |
| 3 | **Condemned** | latest survey `recommended_action = scrap` |
| 4 | **Repair in progress** | a work order in `in_progress` / `on_hold` |
| 5 | **Awaiting QC** | a work order in `completed` |
| 6 | **Repair scheduled** | a work order in `pending` |
| 7 | **Estimate approved — no work order** | estimate `approved` / `partially_approved`, no live work order |
| 8 | **Estimate sent — awaiting approval** | estimate `sent` / `under_review`, or inquiry `estimate_sent` |
| 9 | **Estimate in preparation** | estimate `draft` |
| 10 | **Survey in progress** | inquiry `open` / `in_progress` |
| 11 | **Repaired — available** | every work order `closed`, QC passed |
| 12 | **Sound — available** | survey `no_action` / `monitor`, no repair raised |
| 13 | **Awaiting survey** | in yard, no inquiry for this cycle |

Scope each to the **current gate-in cycle** — `getContainerHistory()` already
groups by cycle, so the same windowing applies.

**Export readiness** then falls out as a derived boolean: status in
{Repaired — available, Sound — available} **and** no active hold **and** not on
hire **and** `containers.status` in {available, in_yard}.

## 5. Where to surface it

| Screen | Change |
| --- | --- |
| Container Inquiry — list | Replace **Condition** with **M&R Status** (the arrival snapshot is the misleading part), add an M&R filter and an "Export ready only" toggle |
| Container Inquiry — detail | Status badge in the header, with the chain rendered as a progress trail |
| Containers master list | Same badge alongside the existing Status column |
| Gate-out / booking allocation | Warn when selecting a container that is not export-ready |

Keep the arrival condition visible in the **detail** view, labelled
"Condition on arrival" — it is useful history, just not a current-state
indicator.

## 6. Implementation notes

- Derive in a service with a **batch** entry point (`forContainers(Collection)`)
  that eager-loads the four chains in one pass each — a per-row derivation on a
  20-row list would be a 5× N+1.
- Filtering by derived status needs the predicate expressed in SQL as well.
  Simplest workable form: a `whereHas`/`whereDoesntHave` chain per status,
  built by the same service so the definition lives in one place.
- If list performance becomes a problem, the fallback is a materialised column
  refreshed by observers on `Inquiry`, `Estimate`, `WorkOrder` and
  `ContainerHold` — but only with the derivation above as the single source of
  truth, and a reconcile command to catch drift (the lesson from Gap 3).
- Worth fixing alongside: have the QC pass write `containers.condition = sound`,
  so the stored condition stops contradicting the repair history.
