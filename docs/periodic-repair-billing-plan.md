# Periodic Repair Billing — Implementation Plan

Consolidated (periodic) repair billing for the Container Yard Management System,
modeled on the Storage & Handling billing module and reusing the existing repair
accounting stack.

## Decisions (locked)

1. **Extend the existing `RepairInvoice` model** — do not build a parallel model.
   Estimate-based (one-shot) and periodic invoices coexist as one type, so
   posting, IRD, numbering, the line model, and show/issue/pay/cancel screens are
   shared.
2. **Work-order status is shown, not enforced.** The preview lists eligible
   estimates with a WO completion badge and a "ready to bill" indicator; a
   "Only completed work orders" toggle filters. Billing incomplete work warns,
   never blocks.
3. **Single invoice-currency per periodic invoice.** Reuses the existing `repair`
   posting path unchanged (amounts in invoice currency, one `exchange_rate`).
4. **Multi-select repair categories.** The user ticks one or more
   `RepairCategory` values to combine in a single invoice (e.g. Washing on its
   own, or Repair + Cleaning combined).

---

## 1. Current state (baseline)

- One approved `Estimate` → one `RepairInvoice`
  (`RepairInvoiceController::create` filters `whereDoesntHave('repairInvoices')`).
- `store()` copies **all** estimate line items into `RepairInvoiceLine`s.
- `RepairInvoiceLine` already carries `estimate_line_item_id`,
  `work_order_line_id`, `container_no`, `charge_code_id`, tax fields.
- Posting: `RepairInvoiceObserver` → `InvoicePostingService::postSafely($m,
  'repair', …)` on status → `issued`. Line-driven by `charge_code_id`
  (fallback account `4003`); VAT is a liability, SSCL embedded in revenue.
- IRD `REP` (`IrdInvoiceNumberService`), numbering `repair_invoice`
  (`NumberSequenceService`) — both already exist.

**The accounting engine is already line-level and estimate-agnostic**, so
periodic billing is chiefly a new selection/preview front-end plus a few nullable
header fields.

---

## 2. Architecture

### 2.1 Extend `RepairInvoice`
A periodic invoice is "a repair invoice not tied to a single estimate, covering
lines pulled from many estimates in a date range, filtered by category." Each
*line* still carries its `estimate_line_item_id`, so posting/IRD/print work
unchanged.

### 2.2 Line-level dedup (no new table)
A line is already billed if it appears on any non-cancelled repair invoice line:

```php
$billedLineIds = RepairInvoiceLine::whereHas('invoice',
        fn ($q) => $q->whereNotIn('status', ['cancelled', 'void']))
    ->whereNotNull('estimate_line_item_id')
    ->pluck('estimate_line_item_id');
```

Partial/category billing falls out for free: bill washing lines now, repair lines
later. **Both** the periodic and the existing one-shot create paths must apply
this exclusion so they can never double-bill.

### 2.3 Multi-select category filter
Filter candidate estimate lines to those whose resolved `repair_category_id` is
in the selected set (`RepairCategory` is the dynamic operational taxonomy;
washing rolls up under "Cleaning & Treatment" via `repair_type='clean_and_treat'`
/ `wash_scope`). "Washing only" = select just that category. Store the selected
category ids on the invoice (`bill_categories` JSON) for display/reprint.

### 2.4 Eligibility + WO status
- **Spine:** approved estimates for the customer with unbilled lines in the
  period, filtered by selected categories.
- **Period basis:** Work-Order completion/closed date when a WO exists, else the
  estimate approval date (selectable in the form).
- **WO status shown per estimate** (pending / in_progress / completed / closed);
  "Only completed" toggle filters; warning (not block) when billing incomplete
  work.

### 2.5 Single currency
User picks invoice currency + rate; preview includes matching-currency estimates.
Amounts stay in invoice currency with one `exchange_rate` → existing `repair`
posting is unchanged.

### 2.6 Reuse everything else
Posting (`repair`), IRD (`REP`), numbering (`repair_invoice`), job dimension
(`yard_job_id`) — all reused. Printing extends the shared
`billing.ird-tax-invoice-pdf` with a repair schedule/annexure.

---

## 3. Data model changes (one migration)

`RepairInvoice`:
- `estimate_id` → **nullable** (periodic spans many estimates).
- `container_id`, `container_no` → **nullable** (multi-container periodic).
- add `billing_mode` enum('estimate','periodic') default 'estimate'.
- add `billing_period_from` date nullable, `billing_period_to` date nullable.
- add `billing_party_id` FK→customers nullable.
- add `bill_categories` json nullable (selected `repair_category_id`s, for
  display/reprint).
- add `period_basis` string nullable ('wo_completed' | 'approved' | 'estimate').

`RepairInvoiceLine`:
- ensure `repair_category_id` is copied onto the line (add to migration/fillable
  if not already present) so the invoice can group/print by category without
  re-joining the estimate.

No other line changes — it already has `estimate_line_item_id`, `container_no`,
charge/tax fields.

---

## 4. Model changes

`RepairInvoice`: add the new fields to `$fillable`; cast
`billing_period_from`/`billing_period_to` to date, `bill_categories` to array;
add `billingParty()` belongsTo(Customer, 'billing_party_id') and a
`billedPartyId()` helper (`billing_party_id ?: customer_id`). Add a
`scopePeriodic()` / `billing_mode` accessor for UI.

---

## 5. Controller + routes

New `App\Http\Controllers\Billing\RepairBillingController` with `index`,
`create`, `preview`, `store` — mirroring `StorageHandlingController`. **Reuse**
`RepairInvoiceController`'s `show`, `issue`, `recordPayment`, `cancel`,
`irdPrint` (both paths produce `RepairInvoice`).

Routes (inside the `billing.` group, named sub-paths before any wildcard):

```php
Route::prefix('repair')->name('repair.')->group(function () {
    Route::get('/',        [RepairBillingController::class, 'index'])->name('index');
    Route::get('/create',  [RepairBillingController::class, 'create'])->name('create');
    Route::post('/preview',[RepairBillingController::class, 'preview'])->name('preview');
    Route::post('/',       [RepairBillingController::class, 'store'])->name('store');
});
```

Permissions: reuse repair-invoice permissions or add
`billing.repair.view/create` gates consistent with existing modules.

---

## 6. Preview engine (`preview`, JSON)

Input: `customer_id`, `billing_party_id?`, `period_from`, `period_to`,
`period_basis`, `categories[]` (repair_category_ids; empty = all),
`only_completed_wo` (bool), `invoice_currency`, `exchange_rate`,
`invoice_type` (tax/non-tax).

Logic:
1. Eligible estimates: `status = 'approved'`, `customer_id` match, currency match,
   whose chosen date basis falls in `[from,to]`; eager-load `lineItems`,
   `workOrders`, `container`.
2. Compute `$billedLineIds` (§2.2); drop already-billed lines.
3. Filter remaining lines to `categories[]` (if any).
4. For each estimate, compute WO status (max/aggregate of its work orders) and a
   `ready` flag (closed/QC-passed). If `only_completed_wo`, drop non-ready.
5. Build per-estimate groups → per-line rows (container, EOR no, category,
   component/MR summary, qty, unit price, line_amount, SSCL, VAT, gross), plus
   per-estimate and grand totals. Convert display via
   `CurrencyService::invoiceDisplayFactor` if foreign.
6. Return JSON: `estimates[]` (each with `wo_status`, `ready`, `lines[]`,
   subtotals), grand totals, currency/rate, and any warnings.

## 7. Store (`store`)

- Validate header + selected `lines[]` (the user's ticked lines).
- Re-derive all totals from the posted lines (never trust the client header);
  re-check each line is still unbilled (concurrency guard).
- `DB::transaction`: `invoice_no = NumberSequenceService::generate('repair_invoice')`;
  `due_date` from the billed party's payment terms
  (`PaymentTermsHelper::dueDate`); create `RepairInvoice` with
  `billing_mode='periodic'`, `estimate_id=null`, period fields,
  `bill_categories`, `billing_party_id`, `status='draft'`; create each
  `RepairInvoiceLine` carrying `estimate_line_item_id`, `work_order_line_id`,
  `repair_category_id`, `container_no`, charge/tax fields.

## 8. UI (`create` + AJAX preview)

Two-column form (mirror S&H) + JSON preview rendered client-side. Unlike S&H's
all-or-nothing, the repair preview is **selectable**:

- Left: customer, billing party, period from/to, **period basis**, **categories
  (multi-select)**, "Only completed WO" toggle, invoice currency + rate, invoice
  type, invoice date.
- Right: eligible estimates as expandable cards — each shows **EOR no,
  container, category breakdown, WO completion badge, "ready to bill"
  indicator, amount, and a checkbox** (per-estimate; expandable to per-line).
  Grand-total summary tiles. Save serializes only the ticked lines into hidden
  `lines[i][…]` inputs (S&H serialization pattern).

## 9. Posting / IRD / numbering / printing

- **Posting:** reuse `InvoicePostingService` type `repair` (line-by-charge-code
  already handles multi-estimate lines). Update AR resolution to honor
  `billedPartyId()` for repair (currently posts against `customer_id`;
  `InvoicePostingService.php:185`).
- **IRD:** `IrdInvoiceNumberService::generate('repair', $invoice->invoice_date)`
  at issue (unchanged).
- **Numbering:** single `repair_invoice` sequence (a periodic invoice can span
  categories, so per-category numbering doesn't apply).
- **Printing:** extend `billing.ird-tax-invoice-pdf` for periodic mode with a
  **repair schedule/annexure**: grouped by container/EOR, showing estimate no,
  repair category, line detail, and per-container subtotals — the standard
  consolidated M&R invoice layout. Add an internal (non-IRD) PDF too.

---

## 10. Phased build (each phase has tests)

1. **Schema + model** — migration (nullable `estimate_id`, new header fields,
   line `repair_category_id`), `RepairInvoice` fillable/casts/relations,
   line-dedup helper. *Tests:* nullable/fillable, dedup query, one-shot path
   now excludes billed lines.
2. **Preview engine** — `RepairBillingController::preview`. *Tests:* date-range +
   category filter + dedup + WO-status + only-completed toggle + currency match.
3. **Store** — periodic invoice creation, totals re-derivation, numbering,
   concurrency re-check. *Tests:* multi-estimate consolidation, partial-category
   billing, no double-bill, cancelled invoice releases its lines.
4. **UI** — create/preview blade with selectable estimates + WO badges.
5. **Posting/IRD linkage** — `billing_party_id` AR resolution; verify periodic
   issue posts a balanced GL, mints IRD, splits revenue per charge code.
   *Tests:* mirror `RepairInvoiceFlowTest` for a periodic invoice.
6. **Printing** — consolidated IRD PDF + schedule/annexure; internal PDF.

---

## 11. Edge cases handled

- Partial-category billing (washing now, repair later) via line dedup.
- No double-billing across both create paths.
- Cancelling/voiding an invoice releases its lines to be re-billed.
- WO not completed → shown + warned, not blocked.
- Zero-amount lines skipped (as today).
- Revised estimates (`version_no`/`parent_estimate_id`) → bill only the current
  version's unbilled lines.
- Mixed currencies → excluded by single-currency selection.
- Concurrency → `store` re-checks each line is still unbilled inside the
  transaction.

---

## 12. Minor open items (safe defaults chosen; change if desired)

- **Numbering:** single `repair_invoice` series (default) vs per-something.
- **Print annexure layout:** grouped by container (default) vs by category.
- **Permissions:** reuse repair-invoice gates (default) vs new
  `billing.repair.*` gates.
