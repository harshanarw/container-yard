# Audit — every `diffIn*` call, and which ones can lie

Prompted by a real bug on Daily Movements: a container recorded as leaving three
days before it arrived reported **"3 days in yard"**. Not a negative — a
confident, plausible, wrong positive.

---

## 1. The defect

`Carbon::diffInDays($other)` returns the **distance** between two moments, not
the signed difference. `$absolute` defaults to true in Carbon 2 and false in
Carbon 3, and Laravel 11 permits either, so the same line can behave differently
on two machines running the same code.

Every clamp of the shape `max(0, (int) $a->diffInDays($b))` in this codebase is
therefore **dead** unless `false` is passed: the value it is guarding against
cannot occur, and the reversal it exists to catch is silently converted into a
positive number instead.

**53 call sites** across `app/` and `resources/`.

---

## 2. Is a reversed interval actually reachable?

Yes, by two routes, neither hypothetical:

**No upper bound on a gate timestamp.** `gate_in_time` is validated as
`['nullable', 'string', 'max:20']` and then handed to `Carbon::parse()`. A
backdate-permitted user typing `2027` for `2026` puts an arrival in the future,
and every "since arrival until now" figure in the app goes positive for a
container that has not arrived.

**No ordering guard between a gate-out and its gate-in.** `UpdateContainerRequest`
has `after_or_equal:gate_in_date` on the *container* record, but the gate
movement path has nothing equivalent. A gate-out earlier than its gate-in is
enterable, and `ContainerMrStatusService::pairGateOuts()` will pair the two when
they share a `yard_job_id`.

---

## 3. Classification

### A — Safe: reversal guarded before the call (4 sites)

| Site | Guard |
| --- | --- |
| `StorageBillingController:183` | `if ($toDate->lt($fromDate)) continue;` immediately above |
| `StorageHandlingController:320` | `$toDate->lt($fromDate) ? 0 : max(1, …)` inline |
| `StorageBillingController:186`, `StorageHandlingController:345` | `$fromDate = max(gate_in_date, periodFrom)`, and `billing_gate_in_date ≤ gate_in_date` by construction |

These are the **right** pattern: an explicit comparison stating what an empty
window means, rather than a clamp hoping to catch it. Both carry comments
explaining that a same-day hire closes the original record at `gate_in − 1` and
must bill zero rather than the `max(1, …)` minimum.

### B — Safe: already signed (3 sites)

`GeneralLedgerController:1122`, `:1128`, `:1395` and
`containers/_form.blade.php:304` all pass `, false`. The AR/AP aging code is the
only place in the app that already understood this, and `:1395` even comments
*"negative = not yet due"* — the sign is load-bearing there.

### C — Fixed: five sites computing the same quantity five ways (5 sites)

All compute days in yard for a gate-in / gate-out pair, and all were unsigned:

| Site | What it fed |
| --- | --- |
| `Reporting/MovementVisits.php` | Daily Movements' new Days in Yard column |
| `ContainerInquiryService:209` | `total_days` and `avg_days` — **summed and averaged**, so one phantom figure inflated a container's whole history |
| `ContainerInquiryController:189` | the inquiry list column and its CSV export |
| `container-inquiry/show.blade.php:447` | the inquiry screen |
| `container-inquiry/print.blade.php:313` | its print view |

Now all five call `App\Support\DaysInYard::between()`. One rule, signed and
clamped, with the reasoning recorded once. Previously they agreed on the
ordinary case and disagreed on every awkward one — the worst way for a figure to
be wrong, because it looks right until two screens are compared.

### D — Reachable but low consequence: "since X, until now" (≈24 sites)

`resources/views/yard/index.blade.php:331`, `yard/storage.blade.php:249`,
`reports/inventory.blade.php:297`, `reports/mr-status.blade.php:216`,
`yard/gate.blade.php:1007`, `Container.php:232`, `MrStatus.php:52`,
`ContainerMrStatusService:299`, `ReportController:98`, `:339`,
`YardController:1853`, `:2013`, `ContainerController:104`, and others of the
same shape.

Reversal needs a **future-dated anchor**, which the missing validation permits.
The consequence is a small wrong positive on a screen — an ageing badge, a
sorting figure, a threshold comparison — rather than a wrong invoice.

**Not changed.** Twenty-four edits to display code, unrunnable here, to correct a
symptom whose cause is one missing validation rule, is a poor trade. Section 5
proposes the cause.

`ContainerMrStatusService:299` is the one worth watching: it compares against an
ageing threshold, so a future `mr_status_at` would raise an overdue flag early.
Reachable only if that column is ever set to a future time, and today it is
always `now()`.

### E — Reachable, needs its own judgement: stored start to stored end (≈13 sites)

| Site | Note |
| --- | --- |
| `Container.php:317` | generic `$start->diffInDays($end)` — callers vary |
| `LessorOnHire.php:93` | `+ 1` inclusive; on-hire to off-hire, both operator-entered |
| `ReeferPlugSession.php:119`, `:109` | plug-in to plug-out, **feeds reefer electricity billing** |
| `ReeferBillingService.php:50`, `:84` | same, on the invoice path |
| `CargoTransferService.php:301`, `:303` | already `max(0, …)` / `max(1, …)` — dead clamps, same shape as the bug |
| `YardController:944`, `:946`, `:2106` | the transfer and storage duplicates of the above |
| `yard/hires/show.blade.php:111` | on-hire to off-hire display |
| `yard/gate.blade.php:1010` | `gate_out_time->diffInDays(container->gate_in_date)` — **arguments reversed relative to every other site**, so it relies on the absolute default to come out positive at all |

**Not changed, and the reefer pair is the one to look at first**: plug-in to
plug-out drives an electricity invoice, and an unsigned diff there converts a
reversed pair into billable hours. It deserves the same treatment as Category C,
but as its own change with its own tests, not folded into an audit.

`yard/gate.blade.php:1010` should be corrected regardless — it reads
`gate_out->diffInDays(gate_in)`, backwards from the convention everywhere else,
and only produces a sensible number because the default is absolute. Passing
`false` there would make it negative, which is a rewrite rather than a flag.

---

## 4. What was changed

Five sites, one new class, no behaviour change for any forward interval. A
reversed pair now reads `0` instead of a plausible positive.

Also `MovementVisits` gained a test that spells out *why* a same-day turnaround
is `0` here and `1` on the invoice, so nobody later "corrects" it into agreement.

---

## 5. The cause, and the fix worth making next

Categories D and E are symptoms of two missing validation rules on the gate
forms:

1. **A gate-in may not be in the future.** No legitimate workflow records an
   arrival that has not happened; backdating is the permitted case and it is, by
   definition, backwards.
2. **A gate-out may not precede its gate-in.** The container record already
   enforces `after_or_equal:gate_in_date`; the movement path does not.

Adding those two guards fixes roughly thirty-five display sites at once, at the
source, and makes the remaining unsigned diffs harmless rather than merely
unlikely to bite. It also stops bad data entering, which no amount of careful
arithmetic downstream can undo.

It is a change to the gate screens — the busiest path in the yard — so it wants
its own branch, its own tests, and a decision about what happens to the rows
already in the database that would fail the new rules. That last question is why
it is not folded in here.
