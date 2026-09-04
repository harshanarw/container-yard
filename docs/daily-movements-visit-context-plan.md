# Daily Movements — showing each movement's other half

Add the paired gate-in to a gate-out row (and the paired gate-out to a gate-in
row), plus the days the container spent in the yard, without changing the report
from a list of movements into a list of visits.

---

## 1. What the report does today

`ReportController::dailyMovements()` selects `gate_movements` rows and the view
prints one row per movement, grouped by customer. Columns: Type, Container No,
Size/Type, Condition, Cargo, Seal, Vehicle Plate, **Gate In**, **Gate Out**,
Location, and the two export-status columns.

**The Gate In and Gate Out columns are not what their headers suggest.** Every
`gate_movements` row carries both a `gate_in_time` and a `gate_out_time` column,
but only the one matching its `movement_type` is ever populated. So on a gate-out
row the Gate In column reads `—`, and on a gate-in row Gate Out reads `—`. The
columns are really "this row's own timestamp, in whichever slot it belongs".

That is exactly the gap: an operator looking at a gate-out has no idea when the
box arrived or how long it sat.

Same in the CSV export — `Gate In Date/Time` and `Gate Out Date/Time` are the
row's own event, so half of each pair is always blank.

---

## 2. The pairing rule already exists — do not write a second one

`ContainerMrStatusService::pairGateOuts()` is **public** and already answers
"which gate-out closed which visit". It resolves in two passes:

1. **By job.** A gate-out carrying the same `yard_job_id` as a gate-in is that
   visit's gate-out, full stop. This is the authoritative link.
2. **Chronologically.** Otherwise the earliest unused gate-out falling between
   this gate-in and the *next* gate-in closes it.

It returns `[gate_in_id => GateMovement]`. This report needs the inverse as well,
which is one `array_flip`-shaped pass, not a second algorithm.

**Reusing it is the point.** M&R status, Container Inquiry and this report would
otherwise each have their own notion of which movements belong together, and the
day a container shows a different history on two screens is the day nobody trusts
either. If the rule is wrong, it should be wrong in one place.

### The constraint that makes this non-trivial

Pairing cannot be done from the rows the report has selected. A gate-out on
3 August pairs with a gate-in on 20 July, which any August filter excludes — and
`ContainerMrStatusService::forGateIns()` already documents the same hazard for
its own paging: *"which gate-out closed a visit depends on the visits around it,
so a page-local view would mis-pair the first and last rows on the page."*

So the visit lookup must load **every movement for the containers appearing in
the result**, not just the filtered ones. One extra query, keyed by container.

---

## 3. What each row gains

| Row type | Gate In | Gate Out | Days in yard |
| --- | --- | --- | --- |
| **Gate-in, still in yard** | its own | — *(In yard)* | gate-in → today, counting |
| **Gate-in, visit closed** | its own | from the pair | gate-in → gate-out |
| **Gate-out** | from the pair | its own | gate-in → gate-out |
| **Gate-out with no gate-in** | — | its own | — |

The row's own event stays visually primary; the paired one is shown muted, so the
report still reads as a list of movements. The requirement was explicit that
gate-ins and gate-outs stay separate rows for export, and this must not quietly
become a visit report.

That last row of the table is not hypothetical. The M&R work established that
containers exist in this yard with a release and no movement history — the
`released_no_movement` status code was added for exactly that. Those rows show
a dash rather than a guess.

---

## 4. "Days in yard" is not "days billed" — and the report must say so

The inventory report already counts operational days this way:

```php
$days = $gateOut ? $gateIn->diffInDays($gateOut) : $gateIn->diffInDays(now());
```

Storage billing does **not** count days like that. It counts inclusive days
across a period, nets off free days, and produces `chargeable_days` — which for a
same-day turnaround is 1 where this figure is 0.

Both are correct for their purpose, and an operator who reads "Days in yard: 12"
as "we can bill 12 days" will be wrong. Mitigations, in order of importance:

1. The column is headed **Days in Yard**, never "Days".
2. A footnote states it is gate-to-gate elapsed days, not chargeable days, and
   points at Storage & Handling for billing figures.
3. The CSV column is named `Days In Yard` for the same reason.

This is worth more attention than it looks: the report is exported and
circulated, and a column of numbers in a spreadsheet loses whatever context the
screen gave it.

---

## 5. Where the code goes

**`App\Services\Reporting\MovementVisits`** — new, small, and the only new logic:

```php
public function for(Collection $movements): array
// [gate_movement_id => ['gate_in' => ?GateMovement,
//                       'gate_out' => ?GateMovement,
//                       'days' => ?int,
//                       'open' => bool]]
```

It loads every movement for the containers present, delegates to
`ContainerMrStatusService::pairGateOuts()` per container, builds the map in both
directions, and computes the elapsed days. No pairing logic of its own.

Keying by `container_id` rather than `container_no`, with a fallback to
`container_no` where `container_id` is null — `forGateIns()` keys by
`container_no` and this report's rows carry both; worth one look at the data
before choosing, and worth a test either way.

`ReportController::dailyMovements()` and `exportMovementsCsv()` each call it once
and hand the map to the view or the generator.

---

## 6. The export, and not breaking it

The CSV feeds downstream reporting and sits beside a CODECO export, so its column
order is not ours alone to rearrange.

**Existing columns keep their exact meaning.** `Gate In Date/Time` and
`Gate Out Date/Time` continue to carry the row's own event, blank on the other
half. Anything currently parsing this file keeps working.

**Three columns are appended at the end:** `Visit Gate In`, `Visit Gate Out`,
`Days In Yard`. Appending rather than inserting is the whole point — a consumer
reading by position is unaffected, and one reading by header name gets the new
data.

The CODECO export is not touched. It is a standards-defined message format and
this is a convenience for humans.

---

## 7. Cost

One extra query per report load, regardless of row count: all movements for the
containers in the result, grouped in PHP.

Worth noting while we are here, though **not proposed as part of this change**:
`dailyMovements()` calls `->get()` with no pagination, so a wide date range
already loads every matching movement into memory and renders them all. Adding
the visit lookup does not make that materially worse — it is one query and a map
— but if the yard has started running month-long ranges, pagination is the thing
to fix, and it should be its own change with its own decision about what
"grouped by customer" means across pages.

---

## 8. Tests

- **Pairing is delegated, not reimplemented** — a gate-out sharing a
  `yard_job_id` with a gate-in pairs by job even when an earlier orphan gate-out
  would win chronologically.
- **A pair spanning the filter window.** Gate-in 20 July, gate-out 3 August,
  report filtered to August: the gate-out row still shows the July gate-in. This
  is the test that fails if anyone "optimises" the lookup to the filtered rows.
- **Multiple visits.** Same container in and out three times; each gate-out row
  shows its own visit's gate-in, not the first or the latest.
- **Still in yard.** A gate-in with no gate-out shows no gate-out and counts days
  to today.
- **Gate-out with no gate-in** shows dashes rather than a guess or a negative.
- **Same-day turnaround is 0 days**, and the test says in words that billing
  counts it as 1, so nobody later "fixes" this to agree with the invoice.
- **A movement with a null timestamp** contributes no days.
- **The CSV keeps its existing column order**, and the three new ones are at the
  end — asserted by index, since that is what a downstream consumer relies on.

---

## 9. Open questions

1. **Days as whole days, or days and hours?** Whole days matches the inventory
   report. A yard doing same-day turnarounds may want `0.5d` or `6h`, which is a
   different column type and worth deciding before it is built.
2. **Should the screen offer a "show only closed visits" filter?** Cheap to add
   once the map exists, and probably what someone wants after a week of using it
   — but not part of this change unless asked.

---

## 10. As built

`App\Services\Reporting\MovementVisits` delegates every pairing decision to
`ContainerMrStatusService::pairGateOuts()` and adds only the inverse map and the
day count. It loads all movements for the containers in the result, not the
filtered rows — the test that pairs an August gate-out with a July gate-in is
what holds that in place.

Both halves of a visit point at the same context object, so a gate-in row and
its gate-out row cannot disagree about when the box arrived or how long it
stayed. Every movement handed in gets an entry, so callers index the map
directly rather than guarding each read.

**One test had to be rewritten to test what it claimed.** The backwards-pair case
(a gate-out timestamped before its gate-in) cannot arise through the
chronological fallback at all: that path requires the gate-out to be at or after
the gate-in, so an early orphan is simply never matched and the visit stays open.
The assertion "days are not negative" therefore passed on an open visit counting
to today, without reaching the arithmetic it was written for. It now forces the
pair through the `yard_job_id` link, which is the only route to it.

Screen: the row's own event prints solid, the paired half muted with a link icon,
and a `Days in Yard` column with a `+` while the count is still rising. A note
under the table states that this is elapsed gate-to-gate time and not chargeable
days.

Export: `Visit Gate In`, `Visit Gate Out` and `Days In Yard` appended after
`Recorded By`. The existing `Gate In Date/Time` and `Gate Out Date/Time` keep
both their position and their meaning, asserted by column index because that is
what a downstream consumer relies on.

