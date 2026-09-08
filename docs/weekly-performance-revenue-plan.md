# Weekly Performance — Revenue

The money twin of the container-count report. Same customers, same weeks, same
date filter — a revenue breakdown for each instead of a size/cargo grid.

---

## 1. What the sample actually is

`WEEKLY_PERFORMANCE_REVENUE_SAMPLE.xlsx`, decoded:

| | |
| --- | --- |
| Title | `PERFORMANCE UPDATE - August 2026` (A2) |
| Columns | A customer · B service · **C:G five week columns** · H row total |
| Header | `C4:G4` merged "WEEKLY PERFORMANCE"; `A4:A5`, `B4:B5`, `H4:H5` merged |
| Week label | row 5, one date per column. Only `C5` filled: **2026-08-09** |
| Body | 24 customers × **4 rows each** — Demounting, Mounting, Storage, Other |
| Customer name | column A on the first of the four rows only, blank beneath |
| Row total | `H = SUM(C:G)` |
| Tail | `OTHER INCOME -RENT` (114), `O/T CHARGES` (115), `TOTAL = SUM(C6:C115)` (116) |

Two things the structure tells us that the brief did not.

**It is a vertical layout, not the count report's banded one.** Eight columns
total — a much simpler sheet to produce, and it should stay simple.

**Two revenue lines belong to the yard, not to any customer.** `OTHER INCOME -
RENT` and `O/T CHARGES` sit below every customer block and are inside the grand
total. A design producing only per-customer categories would be missing the
bottom of the sheet.

Rows 82–93 are empty customer slots carrying live `SUM` formulas — the preparer
left blanks to paste into. A symptom of the manual process, not a requirement.

### The week label is ambiguous, and we should not inherit the ambiguity

`C5` is **Sunday 2026-08-09**. Under the 7-day blocks the count report settled
on, August 2026 splits `1-7 · 8-14 · 15-21 · 22-28 · 29-31` — five bands, which
matches the five columns, but band 1 does not contain the 9th. Under
Monday–Sunday weeks the 9th ends the week 3–9, but August would then need six
bands.

So that date is most likely the day the preparer updated the sheet, not a
generated label. The new report **prints the band's full range** ("01–07 Aug"),
which cannot be misread, and generates weeks through `WeekBreakdown` with the
same `BLOCKS` default the count report uses — so the two reports cut a range
into identical weeks. People will read them side by side.

---

## 2. Naming: this is not a collection report

The brief calls it a Collection Report; the sample says *Performance Update*.
Three different questions live here, and conflating them is the most expensive
mistake available:

| | Question it answers | Source |
| --- | --- | --- |
| **Earned** (accrual) | What did the yard *do* this week, in money? | movements + tariffs |
| **Billed** | What did we *invoice* this period? | invoice headers |
| **Collected** | What cash *arrived*? | receipts, allocations |

The brief specifies **earned** for the movement-driven lines and **billed** for
the rest. Nothing here reads receipts, so nothing here is collection.

**Title: `Weekly Performance — Revenue`.** It sits beside `Weekly Performance —
Container Count` and says what it measures. A genuine collections view — cash
received per customer per week — is a separate report off `receipts` and
`receipt_allocations`, and should not be folded into this one.

---

## 3. The rows, as decided

Eight rows per customer, then a yard-level tail.

| # | Row | Basis | Source |
| --- | --- | --- | --- |
| 1 | **De-mounting** | earned | gate-in movements × handling `lift_off_rate` |
| 2 | **Mounting** | earned | gate-out movements × handling `lift_on_rate` |
| 3 | **Storage** | earned | chargeable days in the week × storage tariff |
| 4 | **Electricity** | billed | reefer invoices, `service_type = long_term` |
| 5 | **PTI** | billed | reefer invoices, `service_type = pti` |
| 6 | **Overtime** | billed | `ot_receipts`, dated by `operational_date` |
| 7 | **Other** | billed | repair + general invoices (M&R, washing, everything else) |
| 8 | **Total** | — | rows 1–7 |

Yard-level tail: `OTHER INCOME — RENT` (blank for now), then a **category
footer** — one row per category summed across all customers — and the grand
total.

The category footer is an addition, not in the sample. It answers "what did the
yard earn in storage this week" directly, and it mirrors the count report's
Mounting/Demounting/Grand footer, so the two reports end the same way.

### De-mounting and Mounting keep the settled mapping

```
De-mounting = Lift Off = movement_type 'in',  dated by gate_in_time
Mounting    = Lift On  = movement_type 'out', dated by gate_out_time
```

That is what `WeeklyPerformanceReport` counts and what
`StorageHandlingController::preview` bills. Three places will now agree —
**this report's De-mounting count must equal the count report's, same week,
same customer.** If they differ, one is broken. Worth a test of its own.

### Two consequences of adding a per-customer Total row

**The grand total can no longer sum every row.** The sample's
`=SUM(C6:C115)` works because every row is a category. Once each block carries
its own Total, summing everything double counts. The grand total sums the
**customer Total rows plus the rent row** — and the generated workbook writes
that formula explicitly rather than relying on a range.

**The block grows from 4 rows to 8.** 24 customers becomes ~192 rows rather
than 117. Fine for a spreadsheet; on screen the blocks want to be visually
separated so a reader can find a customer without counting.

---

## 4. Answers to the four open questions

**1. `OTHER INCOME — RENT` — a blank row for now.** The row is generated,
labelled and inside the grand total, contributing zero, so the sheet keeps the
shape the yard knows and nothing has to move when a source appears. Nothing in
the system models rental income today.

**2. Other = every invoice except Storage & Handling.** Repair, washing, M&R and
all General Invoice categories collapse into one line. No category filter — you
confirmed all of them belong.

> **One residual risk, stated rather than designed around.** `general_invoices.
> category` is a free string, so nothing stops someone raising a General Invoice
> *for storage or handling*. That would double count against the computed lines
> 1–3, silently. Taking every category is your call and I have followed it; if
> the yard ever starts doing that, the fix is a small exclusion list, and this
> paragraph is where to look.

**3. Issued only, no drafts.** Include an invoice when its status is not
`draft`, `cancelled` or `void` — that is `issued`, `paid`, `partially_paid`,
`overdue`. Status-based rather than GL-posting-based: it matches what the
operator sees on screen, and it does not depend on every invoice type's GL
wiring being complete. (`InvoicePosting` rows exist and are the stricter test —
worth revisiting if the two ever disagree, but not the default.)

For OT receipts the vocabulary differs — `draft | generated | paid |
partially_used | fully_used | cancelled | void`, defaulting to `generated`, not
draft. Same rule reads correctly: everything except `draft`, `cancelled`,
`void`.

**4. Overtime by `operational_date` — yes, and it is the right field.**
`ot_receipts` carries `operational_date` (the day the overtime relates to),
`valid_from`/`valid_to` (the window the receipt covers) and `paid_at`.
`operational_date` is the one that puts the income in the week the work
happened, which is what a performance report is for.

Two details that make it safe:

- **An extension copies its parent's `operational_date`**
  (`OtReceiptService::generateExtension()`), so an extension lands in the same
  week as the original rather than drifting into the next one.
- **An extension is `full_new_charge`** — a genuine additional charge for the
  next slab, not a restatement. Both count; there is no double-count to guard
  against.

Because `ot_receipts.customer_id` is always present, Overtime becomes a
**per-customer row** rather than the sample's yard-level one. That is more
informative and consistent with everything else — but it means **the yard-level
`O/T CHARGES` row must go**, or the grand total counts overtime twice. Only one
of the two can exist.

---

## 5. Electricity and PTI as separate rows — yes, and it is nearly free

This was the open question worth researching, and the answer is better than
expected: **the split already exists as a column.**
`2024_01_01_000234_add_service_type_to_reefer_tables.php` added
`service_type enum('pti','long_term')` to `reefer_electricity_tariffs`,
`reefer_plug_sessions` **and** `reefer_electricity_invoices`:

```
pti       → Short-Term PTI charges        (hourly)
long_term → Long-Term reefer electricity  (daily)
```

It is on the invoice header, so the two rows are a `GROUP BY service_type` — no
new modelling, no line-level inspection, no heuristic. The migration also
back-classified existing invoices (hourly lines → `pti`), so history splits
correctly too.

**Why it is worth doing beyond "more informative".** These are different
businesses. Long-term electricity is a daily rate on a plugged box — it tracks
reefer occupancy and is predictable. PTI is an hourly pre-trip inspection
charge — it tracks export bookings and is lumpy. Averaged into one line they
hide each other; a week where PTI collapsed and electricity held would read as
flat. Terminal operators separate plug revenue from inspection revenue for
exactly this reason.

**And it opens a door.** `reefer_plug_sessions` carries the same `service_type`,
which means reefer revenue *could* later be accrued from sessions the way
storage is accrued from stays, putting lines 4 and 5 on the same earned basis as
1–3. Not now — but the axis is already there, and that is worth knowing before
anyone designs around the billed basis.

---

## 6. Storage — the only hard part

Handling is trivial: an event on a date, a rate, a week. Storage accrues per day
across a stay that spans weeks, and has to be sliced.

### The algorithm

For each `YardStorage` stay overlapping the range, for each week band:

```
stayStart   = max(storage.gate_in_date, week.start)
stayEnd     = min(storage.gate_out_date ?? week.end, week.end)
daysInWeek  = stayEnd < stayStart ? 0 : stayEnd->diffInDays(stayStart) + 1

daysBefore  = billing_gate_in_date->diffInDays(stayStart)   // elapsed before this week
chargeable  = ManualPricing::chargeableDays($freeDays, $daysBefore, $daysInWeek)

revenue    += chargeable × rate × currencyMultiplier
```

**`ManualPricing::freeDaysInPeriod()` and `chargeableDays()` already implement
the cumulative free-day rule**, keyed on exactly this "days elapsed before the
window" argument (`app/Services/Billing/ManualPricing.php:34-45`). They are pure
functions. Calling them once per week instead of once per invoice period *is*
the weekly split — the allowance is consumed chronologically across weeks for
free, because each week passes a larger `daysBefore`.

That reuse is the most important design point in this document. A container with
7 free days gating in mid-week 1 must contribute **nothing** in week 1 and full
rate later; re-granting free days per week would understate revenue by roughly a
week per container, every time, invisibly.

The rate comes from the same resolver the invoice uses —
`StorageMasterDetail::resolve($tariff->details, $eqtId, $cargoStatus,
$reeferMode)` — so a NOR prices at the NOR rate here exactly as on an invoice.

### Two deliberate divergences from the invoice

**Already-billed days are not subtracted.** The invoice narrows its window with
`unbilledStorage()` so a period is never billed twice. This report asks what was
*earned*, so every chargeable day counts once, in the week it fell.

**Draft and unissued invoices are irrelevant to lines 1–3**, because those lines
do not read invoices at all. The revenue exists the day the box sat in the yard.

Both are correct for the question the report answers, and both are reasons it
will not reconcile to the ledger.

### Edge cases that must be handled explicitly

- **Still in yard** (`gate_out_date` null) — accrues to the end of each week up
  to today, not to the end of a future-dated range.
- **Same-day in and out** — one day, not zero, matching the invoice's floor.
- **Backwards pairs** (`gate_out < gate_in`) — zero, never negative. Gate Data
  Check exists to find these; the report must not absorb them into a negative.
- **Tariff changes mid-stay** — headers carry `valid_from`. Pricing each week at
  the rate then in force is *more* correct than the invoice's one-tariff-per-run,
  and cheap, since we already iterate weeks.
- **No tariff configured** — a zero meaning "no rate set up" and a zero meaning
  "quiet week" must not look the same, or missing configuration reads as a slow
  week. Surface it, as `TariffRateGuard` does on the invoice.

---

## 7. Customer identity — checked, and consistent

`StorageHandlingInvoice` bills a `shipping_line_id`, which reads like a
different entity — but `StorageHandlingController:158` filters
`YardStorage::where('customer_id', $shippingLine->id)`, so it points at the same
`customers` table. `gate_movements.customer_id`, `yard_storages.customer_id`,
`repair_invoices.customer_id`, `general_invoices.customer_id`,
`reefer_electricity_invoices.customer_id` and `ot_receipts.customer_id` are all
the same key.

One customer id runs through the whole report, and it lists the same customers
as the count report.

The sample's trailing `OTHER` customer is a manual catch-all. Its analogue is
movements with a null `customer_id`; the count report already surfaces those
through `unmapped()`, and this report does the same rather than dropping the
revenue.

---

## 8. Currency

Every figure in base currency (LKR), converted at each source's **own stored
exchange rate** — never today's rate, which would make last month's report
change value overnight.

`storage_invoices`, `storage_handling_invoices` and
`reefer_electricity_invoices` carry `total_value` (already base currency).
`repair_invoices` and `general_invoices` carry `grand_total` + `exchange_rate`
and must be converted. Tariffs in USD convert through
`CurrencyService::tariffMultiplier()`. One helper, not five call sites.

---

## 9. What will not reconcile, and the note that says so

A report that quietly disagrees with the invoices is trusted until someone
checks, and distrusted permanently after. So the page carries a basis note, and
the export carries it too:

> De-mounting, Mounting and Storage are **earned** revenue, computed from gate
> movements and the tariffs in force — they do not depend on invoicing having
> run. Electricity, PTI, Overtime and Other are **billed**, taken from issued
> documents dated in the period. This report will not tie to the invoice ledger
> or the GL.

---

## 10. Reuse map

Almost nothing here is new machinery.

| Need | Existing |
| --- | --- |
| Week bands from a date range | `WeekBreakdown` (`BLOCKS` default) |
| Which week a date falls in | `WeekBreakdown::indexFor()` |
| Customer list, unmapped handling | `WeeklyPerformanceReport::customers()` shape |
| Free-day allocation | `ManualPricing::freeDaysInPeriod()` / `chargeableDays()` |
| Storage rate incl. NOR | `StorageMasterDetail::resolve()` |
| Handling rate | `HandlingTariff->rates` by size |
| Electricity / PTI split | `reefer_electricity_invoices.service_type` |
| USD → LKR | `CurrencyService::tariffMultiplier()` |
| Missing-tariff reporting | `TariffRateGuard` |
| Flat CSV | `TabularExport` |

New: `app/Services/Reporting/WeeklyRevenueReport.php`,
`app/Support/Export/WeeklyRevenueWorkbook.php`, a controller action, a Blade
view, a route, and a `reports.weekly-revenue` permission in `config/modules.php`.

The workbook is a **new** class, not a parameterisation of
`WeeklyPerformanceWorkbook` — the layouts share nothing but weeks across the
top, and forcing one class to emit both leaves a knot neither report benefits
from.

---

## 11. Phases

**Phase 1 — the numbers.** `WeeklyRevenueReport`: seven category lines plus the
per-customer total, week allocation, currency conversion, the missing-tariff
signal, and the category footer. Pure service, verified against hand-computed
cases before any screen exists. All the risk is here.

**Phase 2 — the screen.** Preview with the same filter bar as the other reports
(date range, customer, week rule), the vertical layout with visually separated
customer blocks, the basis note, and the footer.

**Phase 3 — the export.** The xlsx in the sample's shape — extended to eight
rows per customer — plus a flat CSV, and the rent placeholder row.

**Phase 4 — the printed page.** PDF, on the templates just corrected.

Phase 1 before Phase 2 for the same reason as last time: a screen built on
unchecked numbers makes wrong numbers look official.

---

## 12. Tests

The ones that would actually catch a defect:

- **Cross-report equality** — De-mounting revenue ÷ rate equals the count
  report's De-mounting count, same week, same customer. Ties the two reports
  together permanently.
- A stay spanning three weeks with 7 free days puts **zero** in week 1 and full
  chargeable days in weeks 2 and 3 — the easiest thing here to get wrong.
- A container still in the yard accrues to today, not to the end of a
  future-dated range.
- Same-day in and out bills one day, not zero.
- A backwards pair contributes zero, never negative.
- A NOR prices at the NOR storage rate, not the operating rate.
- A Storage & Handling invoice in the period does **not** appear in Other.
- A reefer invoice with `service_type = pti` lands on the PTI row and not on
  Electricity, and vice versa.
- An OT extension and its parent both count, in the same week.
- A draft invoice contributes nothing; an issued one contributes.
- The per-customer Total equals the sum of its seven category rows.
- The grand total equals the sum of the customer Totals plus rent — **and does
  not double count** by also summing category rows.
- A customer with movements but no tariff shows the missing-rate signal, not a
  zero.
- Weeks are identical to the count report's for the same range and rule.

---

## 13. Settled

1. `OTHER INCOME — RENT` is a generated blank row; no source exists yet. (§4.1)
2. Other absorbs every invoice except Storage & Handling — M&R, washing and all
   General categories. Residual double-count risk noted, not designed around.
   (§4.2)
3. Issued only: not `draft`, `cancelled` or `void`. (§4.3)
4. Overtime is a **per-customer row** dated by `operational_date`, which means
   the yard-level `O/T CHARGES` row is dropped — keeping both would double
   count. (§4.4)
5. Electricity and PTI are separate per-customer rows, split on the existing
   `service_type` column. (§5)
6. Each customer block ends with a **Total** row, and the grand total sums those
   rather than every row. (§3)
