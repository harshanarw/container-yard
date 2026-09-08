# Weekly Performance — Revenue

The money twin of the container-count report. Same customers, same weeks, same
date filter — four revenue lines each instead of a size/cargo grid.

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

Two things the structure tells us that the brief does not.

**It is a vertical layout, not the count report's banded one.** Eight columns
total. That is a much simpler sheet to produce than the Weekly Performance
workbook, and it should stay simple — no merged bands, no frozen mid-sheet
panes beyond the header.

**There are two revenue lines that belong to the yard, not to any customer.**
`OTHER INCOME - RENT` and `O/T CHARGES` sit below every customer block and are
inside the grand total. They are not a fifth and sixth category — they are
yard-level rows. Any design that only produces per-customer categories will be
missing the bottom of the sheet.

Also worth noting: rows 82–93 are empty customer slots carrying live `SUM`
formulas — the preparer left blanks to paste into. That is a symptom of the
manual process, not a requirement.

### The week label is ambiguous, and we should not inherit the ambiguity

`C5` is **Sunday 2026-08-09**. Under the 7-day blocks the count report settled
on, August 2026 splits `1-7 · 8-14 · 15-21 · 22-28 · 29-31` — five bands, which
matches the five columns C:G, but band 1 is 1–7 and does not contain the 9th.
Under Monday–Sunday weeks the 9th ends the week 3–9, but August 2026 would then
need six bands.

So the date in `C5` is most likely the day the preparer updated the sheet, or a
week-ending marker they wrote by hand — not a generated label. Rather than pick
an interpretation, **the new report should print the band's full range**
("01–07 Aug"), which cannot be misread. Week generation reuses `WeekBreakdown`
with the same `BLOCKS` default the count report uses, so the two reports cut
the same range into the same weeks — which matters, because people will read
them side by side.

---

## 2. Naming: this is not a collection report

The brief calls it a Collection Report; the sample is titled *Performance
Update*. Those are three different things, and conflating them is the most
expensive mistake available here:

| | Question it answers | Source |
| --- | --- | --- |
| **Earned** (accrual) | What did the yard *do* this week, in money? | movements + tariffs |
| **Billed** | What did we *invoice* this period? | invoice headers |
| **Collected** | What cash *arrived*? | receipts, allocations |

The brief specifies **earned** for three lines ("calculate based on the weekly
movements and relevant tariffs") and **billed** for the fourth ("pick from the
Invoices billed on relevant date periods"). Nothing here reads receipts, so
nothing here is collection.

**Recommended title: `Weekly Performance — Revenue`.** It sits beside
`Weekly Performance — Container Count` and says what it measures. If a genuine
collections view is wanted later — cash received per customer per week — that
is a separate report off `receipts` and `receipt_allocations`, and it should not
be folded into this one.

---

## 3. The four lines, and where each comes from

| Line | Basis | Source |
| --- | --- | --- |
| **Demounting** | earned | gate-in movements × handling tariff `lift_off_rate` |
| **Mounting** | earned | gate-out movements × handling tariff `lift_on_rate` |
| **Storage** | earned | chargeable days falling in the week × storage tariff |
| **Other** | billed | repair / general / reefer-electricity invoices dated in the week |

Demounting and Mounting already have a settled mapping, and this report must not
invent a second one:

```
Demounting = Lift Off = movement_type 'in',  dated by gate_in_time
Mounting   = Lift On  = movement_type 'out', dated by gate_out_time
```

That is what `WeeklyPerformanceReport` counts and what
`StorageHandlingController::preview` bills. Three places will now agree on it,
which is the point — **the revenue report's Demounting count must equal the
count report's Demounting count for the same week and customer.** If they ever
differ, one is broken. That equality is worth a test of its own.

### The mixed basis is a real decision, not an oversight

Three lines are computed from events; one is read from invoices. The result is
that **this report will not tie to the invoice ledger**, and everyone reading it
needs to know why:

- Storage and handling are invoiced **monthly in arrears**. A weekly report
  built from invoices would show nothing for three weeks and a spike in the
  fourth — useless as an operational signal.
- Repair and washing have no comparable event stream that is priced at the time
  of work, so the invoice is the first place the money exists.

That is a legitimate reason, and it is standard practice in terminal operations
to run an operational revenue view separately from the billed/GL view. What is
*not* acceptable is leaving it implicit. The report must carry a visible basis
note, and the three computed lines should be labelled as computed.

**There is an alternative worth putting on the table:** repair revenue *could*
be accrued from completed work orders rather than invoices, which would make all
four lines consistent and would show repair revenue in the week the work
happened rather than the week someone raised the paperwork. It is more work and
it diverges from the brief, so it is not the default — but if the yard's repair
invoicing lags the work by weeks, this is the change that makes the report
honest, and it should be a conscious choice rather than an omission.

---

## 4. Storage — the only hard part

Handling is trivial: an event happened on a date, it has a rate, the date lands
in a week. Storage accrues per day across a stay that spans weeks, and has to be
sliced.

### The algorithm

For each `YardStorage` stay overlapping the range, and each week band:

```
stayStart   = max(storage.gate_in_date, week.start)
stayEnd     = min(storage.gate_out_date ?? week.end, week.end)
daysInWeek  = stayEnd < stayStart ? 0 : stayEnd->diffInDays(stayStart) + 1

daysBefore  = billing_gate_in_date->diffInDays(stayStart)     // elapsed before this week
chargeable  = ManualPricing::chargeableDays($freeDays, $daysBefore, $daysInWeek)

revenue    += chargeable × rate × currencyMultiplier
```

**`ManualPricing::freeDaysInPeriod()` and `chargeableDays()` already implement
the cumulative free-day rule**, keyed on exactly this "days elapsed before the
window" argument (`app/Services/Billing/ManualPricing.php:34-45`). They are pure
functions. Calling them once per week instead of once per invoice period is the
whole of the weekly split — the free-day allowance is consumed chronologically
across the weeks for free, because each week passes a larger `daysBefore`.

That reuse is the single most important design point in this document. A
container with 7 free days gating in mid-week 1 must contribute **nothing** in
week 1 and full rate later; getting that wrong by re-granting free days per week
would understate revenue by roughly a week's worth per container, every time.

The rate comes from the same resolver the invoice uses —
`StorageMasterDetail::resolve($tariff->details, $eqtId, $cargoStatus,
$reeferMode)` — so NOR containers price at the NOR rate here exactly as they do
on an invoice.

### Two deliberate divergences from the invoice

**Already-billed days are not subtracted.** The invoice narrows its window with
`unbilledStorage()` so a period is never billed twice. This report asks what was
*earned*, so every chargeable day counts once, in the week it fell. A container
whose storage was corrected and re-billed shows its days once here and possibly
across two invoices there.

**Draft and unissued invoices are irrelevant to these three lines**, because
they are not read at all. The revenue exists the day the box sat in the yard.

Both are correct for the question the report answers, and both are reasons it
will not reconcile to the ledger. Say so on the page.

### Edge cases that must be handled explicitly

- **Still in yard** (`gate_out_date` null) — accrues to the end of each week up
  to today, not to the end of the range if the range is in the future.
- **Same-day in and out** — one day, not zero. The invoice already floors at 1;
  the report must match.
- **Backwards pairs** (`gate_out < gate_in`) — zero, never negative. The Gate
  Data Check module exists to find these; the report must not silently absorb
  them into a negative number.
- **Tariff changes mid-stay** — `storage_master_headers` carry `valid_from`. A
  stay spanning a rate change should price each week at the rate in force. The
  invoice resolves one tariff per invoice; doing it per week is *more* correct,
  and cheap, since we are already iterating weeks.
- **No tariff configured** — the invoice flags this through `TariffRateGuard`.
  The report must show it too: a zero that means "no rate configured" and a zero
  that means "nothing happened" cannot look the same, or the yard will read
  missing configuration as a quiet week.

---

## 5. Other — from invoices, without double counting

Sources, all keyed on `customer_id` and `invoice_date`:

| Model | Include | Note |
| --- | --- | --- |
| `RepairInvoice` | yes | repair and wash — the sample's headline case |
| `GeneralInvoice` | **by category** | see below |
| `ReeferElectricityInvoice` | yes | plug / PTI revenue |
| `StorageHandlingInvoice` | **never** | its storage and handling are lines 1–3 |
| `StorageInvoice` | **never** | same reason |

**The double-count guard is the point of this table.** If a General Invoice can
carry a storage or handling charge — and it has a `category` column, so it can —
then including it wholesale double counts against the computed lines. The
category filter is not a nicety; it is the difference between a correct report
and one that overstates revenue with no visible symptom.

Status: exclude `cancelled` and `void`. Include `draft` — the work is done and
the invoice merely unissued — but make it a visible filter, because a month-end
reviewer will want the issued-only number.

Currency: `storage_invoices`, `storage_handling_invoices` and
`reefer_electricity_invoices` carry `total_value` (base currency, already
converted). `repair_invoices` and `general_invoices` carry `grand_total` +
`exchange_rate` and must be converted. One helper, not five call sites.

---

## 6. The two yard-level rows

| Row | Source | Confidence |
| --- | --- | --- |
| `O/T CHARGES` | `OtReceipt.total_amount`, dated by `operational_date` | **good** — the module exists |
| `OTHER INCOME - RENT` | *no obvious source* | **needs a decision** |

Overtime maps cleanly: `ot_receipts` has `customer_id`, `operational_date`,
`total_amount` and a status. Note the sample puts it on a yard-level row even
though the data is per-customer — so either it aggregates across customers
(matching the sample) or it becomes a fifth per-customer category. The sample
says yard-level; I would follow the sample and keep the per-customer detail for
a drill-down later.

**Rent has no home in the system.** Nothing models yard or equipment rental. The
options are: a General Invoice category reserved for it (cleanest — it is
invoiced revenue like any other), a manual entry field on the report screen
(matches how the sheet works today, but it is then not a report of record), or
leave the row present and empty until there is a source. This needs an answer
before Phase 3; it does not block Phases 1–2.

---

## 7. Customer identity — checked, and it is consistent

A worry worth recording as settled. `StorageHandlingInvoice` bills a
`shipping_line_id`, which reads like a different entity from the count report's
`customer_id` — but `StorageHandlingController:158` filters
`YardStorage::where('customer_id', $shippingLine->id)`, so `shipping_line_id`
points at the same `customers` table. `gate_movements.customer_id`,
`yard_storages.customer_id`, `repair_invoices.customer_id` and
`general_invoices.customer_id` are all the same key.

So the report groups by one customer id throughout, and the count report and the
revenue report list the same customers.

The sample's trailing `OTHER` customer is a manual catch-all. Its analogue here
is movements with a null `customer_id` — the count report already surfaces those
through `unmapped()`, and this report should do the same rather than silently
dropping the revenue.

---

## 8. Currency

Every figure in base currency (LKR), converted at each source's own stored
exchange rate — never at today's rate, which would make last month's report
change value overnight. Tariffs in USD convert through
`CurrencyService::tariffMultiplier()`, the same path the invoice uses.

The column header states the currency once. No mixed-currency cells.

---

## 9. What will not reconcile, and the note that says so

A report that quietly disagrees with the invoices will be trusted until the
first time someone checks, and distrusted permanently after. So the page carries
a short basis note, and the export carries it too:

> Demounting, Mounting and Storage are **earned** revenue, computed from gate
> movements and the tariffs in force — they do not depend on invoicing having
> run. Other is **billed** revenue, taken from invoices dated in the period.
> This report will not tie to the invoice ledger or the GL.

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
| USD → LKR | `CurrencyService::tariffMultiplier()` |
| Missing-tariff reporting | `TariffRateGuard` |
| Banded xlsx export | `WeeklyPerformanceWorkbook` — *pattern*, not the class |
| Flat CSV | `TabularExport` |

New: `app/Services/Reporting/WeeklyRevenueReport.php`,
`app/Support/Export/WeeklyRevenueWorkbook.php`, a controller action, a Blade
view, a route, and a `reports.weekly-revenue` permission in `config/modules.php`.

The workbook is a *new* class, not a parameterisation of the existing one — the
layouts have nothing in common beyond both being weeks across the top, and
forcing one class to emit both would leave a knot neither report benefits from.

---

## 11. Phases

**Phase 1 — the numbers.** `WeeklyRevenueReport` with the four per-customer
lines, week allocation, currency conversion, and the missing-tariff signal.
Pure service, verified against hand-computed cases before any screen exists.
This is where the risk is; everything after is presentation.

**Phase 2 — the screen.** Preview with the same filter bar as the other reports
(date range, customer, week rule, draft toggle), the vertical layout, the basis
note, per-customer subtotals and the grand total.

**Phase 3 — the export and the tail rows.** The xlsx matching the sample's shape
plus a flat CSV, and the `O/T CHARGES` / `OTHER INCOME - RENT` rows once rent
has a source.

**Phase 4 — the printed page.** PDF, following the templates just corrected.

Phase 1 before Phase 2 for the same reason as last time: a screen built on
numbers nobody has checked is a screen that makes wrong numbers look official.

---

## 12. Tests

The ones that would actually catch a defect:

- **Cross-report equality** — Demounting revenue ÷ rate equals the count
  report's Demounting count, same week, same customer. Ties the two reports
  together permanently.
- A stay spanning three weeks with 7 free days puts **zero** in week 1 and full
  chargeable days in weeks 2 and 3 — the free-day rule, which is the easiest
  thing here to get wrong.
- A container still in the yard accrues to today, not to the end of a
  future-dated range.
- Same-day in and out bills one day, not zero.
- A backwards pair contributes zero, never negative.
- A NOR prices at the NOR storage rate, not the operating rate.
- A Storage & Handling invoice in the period does **not** appear in Other.
- A cancelled invoice contributes nothing; a draft contributes only when the
  draft toggle is on.
- A customer with movements but no tariff shows the missing-rate signal rather
  than a zero.
- Weeks are identical to the count report's for the same range and rule.

---

## 13. Needs an answer before it is built

1. **`OTHER INCOME - RENT`** — General Invoice category, manual entry, or leave
   it empty for now? (§6)
2. **Which General Invoice categories are "Other"?** Everything except any that
   carry storage or handling, but I need the yard's category list to name them.
   (§5)
3. **Draft invoices in Other by default — yes or no?** My recommendation is yes
   with a toggle; a finance reader may want the opposite default. (§5)
4. **Does `O/T CHARGES` stay a yard-level row, or become a fifth per-customer
   category?** The sample says yard-level. (§6)

None of these block Phase 1, which is the part worth starting.
