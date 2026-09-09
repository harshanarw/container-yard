# Container stock as at a date

"What was in the yard on 30 September, for this shipping line?" The Inventory
report cannot answer that, and it cannot be made to. This is why, and what to
build instead.

---

## 1. Why Inventory cannot do this

Inventory reads the **`containers` master table**:

```php
return Container::with('customer')
    ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
    ->when($request->status,      fn ($q, $v) => $q->where('status', $v))
    ->when($request->date_from,   fn ($q, $v) => $q->whereDate('gate_in_date', '>=', $v))
    ->when($request->date_to,     fn ($q, $v) => $q->whereDate('gate_in_date', '<=', $v))
```

Every column it filters on describes **now**, and three of them are overwritten
on every visit:

| Column | What it holds | Why an as-at query breaks |
| --- | --- | --- |
| `status` | the container's status **today** | a box in the yard in September and released in October reads `released` — it is filtered out of September |
| `gate_in_date` | the **latest** arrival | a box that has been in and out five times keeps only the fifth; the September visit is gone |
| `gate_out_date` | the **latest** departure | same |
| `customer_id` | the master's **current** party | the field the custody work spent this month removing from every operational decision |

And the date filters do not mean what the requirement means. `date_from` /
`date_to` filter on `gate_in_date`, so they answer *"which containers arrived
between these dates"* — not *"which containers were here on this date"*. A box
that arrived in July and was still sitting there on 30 September does not
appear in a 1–30 September Inventory run at all.

**So this is not a missing filter. It is a missing dimension.** The master table
holds one row per container with no history in it; the question needs history.

---

## 2. Where the answer actually lives

`gate_movements` is the visit ledger, and it already holds everything required:
one row per gate event, each carrying its own `gate_in_time` / `gate_out_time`,
its own `customer_id`, and its own `condition`, `cargo_status`, `size`,
`container_type`, `reefer_mode` and location as they were **at that moment**.

The rule is one sentence:

> A container was in the yard at date **D** if it has a gate-in at or before D
> whose paired gate-out is either absent or later than D.

That is a point-in-time query over an event log — the same shape as a stock
ledger or a trial balance, and the standard way any terminal system answers
"stock as at". It needs no new table and no nightly snapshot job.

### The pairing already exists, and must be reused

`ContainerMrStatusService::pairGateOuts()` is the yard's canonical matcher: an
explicit shared `yard_job_id` first, then a time window bounded by the next
gate-in. The M&R cycle logic, the container inquiry screen and
`containers:fix-gate-custody` all use it. A stock report that paired movements
its own way would eventually disagree with all three, and the disagreement
would surface as a customer dispute rather than as a bug report.

### And the customer comes from the visit

`gate_movements.customer_id` on the **gate-in**, resolved through
`ContainerCustodyService` — job first, then the movement. Never the master.
This is the whole point of the requirement: the customer asking is the shipping
line who *had* boxes there in September, which is not necessarily who the
master says owns them today.

---

## 3. So: extend Inventory, or a new report?

**A new report.** Three reasons, in order of weight.

1. **Different grain.** Inventory is one row per *container*. Stock as at is one
   row per *visit* — the same box can legitimately appear twice in a date range
   if it left and came back, and on an as-at date it appears once, for the visit
   that was open. Bolting a second grain onto one screen produces a report
   nobody can reconcile.

2. **Different source, and Inventory's source is right for its own job.**
   Inventory answers "what have I got and what state is it in" — for that, the
   live master is correct and fast. Rewriting it to read movements would make
   today's answer slower and no better.

3. **Different audience.** This one is sent to a shipping line, so it is closer
   to a statement than to an operations screen: it needs an as-at date in the
   header, a customer name, and totals that foot.

Inventory keeps its job. Its date filters should be **relabelled** to
"Arrived between", because they have always meant that and the label is what
made this look like a gap in the first place.

---

## 4. What the report contains

**Parameters:** as-at date (default yesterday), customer (optional, all when
blank), and optional size / type / cargo status / condition filters.

**One row per container in the yard on that date:**

| Column | Source |
| --- | --- |
| Container No | gate-in movement |
| Size / Type | the movement, not the master — it is what arrived |
| Cargo status | the movement (laden / empty **as at that visit**) |
| Reefer mode | the movement, so a NOR reads as one |
| Condition | the movement |
| Customer | `ContainerCustodyService`, from the visit |
| Gate In | the visit's arrival |
| Days in yard **as at D** | D minus gate-in, **not** minus today |
| Location | the movement's row/bay/tier |
| Job / job type | the visit's job |

`Days in yard as at D` is the column that makes this a stock report rather than
a list, and it is the one most likely to be got wrong: it must count to the
as-at date, never to `now()`.

**Summary above the rows:** total, then a breakdown by size and by cargo status,
because "how many 40s did we have" is the second question every time.

---

## 5. Five decisions, with recommendations

**1. Boundary on the day itself.** A box that gated out at 14:00 on 30
September — in stock on the 30th, or not? Recommendation: **stock is measured at
end of day**, so it is *out*. Consistent with `DateWindow`'s inclusive days and
with how the storage bill counts the gate-out day.

**2. Containers with no gate-in.** Gate Data Check's `NO_GATE_IN` already finds
these. They cannot be placed in time, so they cannot appear. Recommendation:
**exclude, and show a footnote with the count**, linking to Gate Data Check.
Silence here would mean a customer's count is short with no explanation.

**3. In-yard but not physically present.** A container in repair, or on hire, is
in the yard but not available. Recommendation: **include it, with a Stage
column**, because the customer asked what was *in the yard*. Availability is a
second question and `available-stock` already answers it.

**4. Timezone.** `config/app.php` on live is still `'timezone' => 'UTC'`, so
"30 September" on the server is 5½ hours off the yard. A box gated in at 02:00
on 1 October Sri Lanka time is 30 September in UTC, and would appear in the
wrong month's stock. **This report is worth nothing until that is fixed** — it
is the one item on this list that is a prerequisite rather than a preference.

**5. Deleted movements.** `gate_movements` has no soft deletes, so a deleted
gate-out makes a container look permanently in stock. The gate-out delete now
restores the visit, and Gate Data Check reports the shape, so the exposure is
small — but the footnote in decision 2 should cover it too.

---

## 6. Phases

**Phase 1 — the query, headless.** `ContainerStockAsAt::rows($date, $filters)`:
pairs movements via `pairGateOuts()`, resolves the customer through
`ContainerCustodyService`, returns visit rows. Pure enough to test directly, and
the whole of the risk lives here.

**Phase 2 — the screen.** `reports.container-stock`, its own
`reports.container-stock.view` permission, as-at date and customer pickers,
summary tiles, the rows.

**Phase 3 — the exports.** XLSX and CSV, mirroring the columns, with the as-at
date in the filename and in the sheet header — a stock file with no date on it
is worse than no file.

**Phase 4 — the label fix on Inventory.** Rename its date filters to "Arrived
between", and add a line pointing at this report for as-at questions.

**Not in scope:** a movements-between-dates report (opening balance, in, out,
closing balance). That is a genuinely useful second report and the same service
would produce it, but it answers a different question and should not be
smuggled into this one.

---

## 7. Performance

One indexed pass. `gm_type_gate_in_idx` and `gm_type_gate_out_idx` (migration
000303) already cover `movement_type` + time, which is exactly the access path.
Candidates are narrowed by `gate_in_time <= D` in SQL, and only those containers
have their movements paired in PHP. On a yard of this size that is a few
thousand rows and well under a second; if it ever is not, the fix is a
materialised daily snapshot table, which this design can be swapped to without
the screen changing.

---

## 8. Tests

- A container gated in before D and never out appears.
- Gated in before D, out after D: appears.
- Gated in before D, out before D: absent.
- Gated out **on** D: absent (decision 1), pinned so the boundary cannot drift.
- Gated in after D: absent.
- In and out twice, in again before D: appears once, for the open visit.
- Days in yard counts to D, not to today — with a frozen clock well after D.
- The customer is the visit's, not the master's: master says X, gate-in says Y,
  the report says Y and a filter on Y finds it.
- Cargo status and reefer mode come from the movement, so a box that arrived
  laden still reads laden after the master was later edited.
- A container with no gate-in is excluded and counted in the footnote.
- Size and cargo filters narrow the rows and the summary together.
