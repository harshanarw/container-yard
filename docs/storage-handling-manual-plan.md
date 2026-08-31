# Storage & Handling — Manual Pricing

**Requirement.** Some bills are priced case by case rather than from the agreed
tariff: the operator decides the free time, the storage rate and the handling
rates at the moment the invoice is raised. Everything else about the invoice —
fields, structure, printing, posting — stays as it is today.

---

## Decisions (settled)

1. **One module, two entry points.** A `pricing_mode` on the existing header,
   not a second invoice type. Operators see a separate *"Storage & Handling —
   Manual"* menu item and a distinct URL; behind it is the same controller,
   table, numbering sequence, GL path and print template. §3 explains why.
2. **Rate matrix, with per-line override.** Entering a rate against 200
   containers one at a time is not usable. One row per equipment type × size
   actually present in the period fills every matching line; the per-line boxes
   remain for the exceptions.
3. **Free time on the header only**, stored in its own column, defaulting to 0.
   Lines are not individually editable — they recalculate from the header.
4. **Charge codes are fixed, not chosen.** Storage lines take `STC`, handling
   lines take `LOLO` — the same defaults the tariff screens pre-select. Tax
   follows the charge code, exactly as the tariff flow already does.
5. **The customer-facing PDF says nothing about pricing mode.** The customer
   sees agreed numbers; how they were arrived at is internal.
6. **A manual bill may be raised even where a valid tariff exists.** Sometimes
   the tariff is right for the customer and wrong for one container. The badge
   records what happened rather than forcing an operator to invent a reason.
7. **Draft invoices are editable; issued or posted ones are not.** See §7.

---

## 1. What already exists

### 1.1 The line table already stores what manual mode needs

`storage_handling_invoice_lines` snapshots the pricing per line:

```
storage_free_days, storage_daily_rate, storage_subtotal
has_lift_off, lift_off_rate, has_lift_on, lift_on_rate
charge_code_id, tax1_rate, tax2_rate
handling_charge_code_id, handling_tax1_rate, handling_tax2_rate
```

Nothing downstream re-reads a tariff. Printing, GL posting, IRD numbering,
credit notes and revenue reporting all read the stored line. **A manual invoice
and a tariff invoice are identical once saved** — only the way those columns get
filled differs. No new line columns are needed.

### 1.2 `store()` already accepts posted rates

`StorageHandlingController::store()` validates the rates as *input*
(`lines.*.storage_daily_rate`, `lines.*.lift_off_rate`, …) and then persists
what was posted. Before persisting it calls `guardHandlingRates()`, which
re-resolves every rate from the tariff and blocks the save when one is missing.

That guard is the only thing standing between today's behaviour and manual
pricing, and it is already an isolated method. Manual mode swaps it for a
different guard rather than rewriting the save.

### 1.3 The charge code defaults are hardcoded in two Blade files

| Screen | Default | Location |
| --- | --- | --- |
| Storage Tariff → Add Rate | `STC` — Storage Charges | `masters/storage-tariff/show.blade.php` |
| Handling Tariff → Add Rate | `LOLO` — Lift Off / Lift On | `masters/handling-tariff/show.blade.php` |

Both are seeded system codes carrying a tax code, so `tax1_rate` / `tax2_rate`
follow automatically. `LOLO` covers **both** lift directions, which matches the
line's single `handling_charge_code_id` — so handling needs no schema change.

### 1.4 Free days are consumed cumulatively

```php
$daysBeforePeriod  = max(0, $gateIn->diffInDays($fromDate));
$freeDaysRemaining = max(0, $freeDays - $daysBeforePeriod);
$freeDaysInPeriod  = min($totalDays, $freeDaysRemaining);
$chargeableDays    = max(0, $totalDays - $freeDaysInPeriod);
```

Free days are spent from the original gate-in (`billing_gate_in_date`), **not
granted afresh each period**. A container gated in on 1 January with five free
days, billed for February, gets none — all five were used in January.

This is correct and manual mode must use the same arithmetic. Granting the
header value per period would hand a monthly-billed customer their free days
every month.

---

## 2. What changes

| Layer | Change |
| --- | --- |
| Header | `pricing_mode` (`tariff` \| `manual`), `manual_free_days` |
| Lines | **nothing** — already stores rate, free days and charge codes |
| Guard | `guardHandlingRates()` runs for tariff mode; a manual guard replaces it |
| Charge codes | Resolved from constants, not from a tariff row |
| Permission | `billing.storage-handling.manual`, separate from `create` |
| Routes | `/billing/storage-handling/manual/create` → same controller, `mode=manual` |

---

## 3. Why one module rather than two

Duplicating the module would fork the things that must not be forked:

- **IRD invoice numbering.** A statutory sequence. Two tables minting from it is
  a compliance risk, not an aesthetic one.
- **GL posting**, via `StorageHandlingInvoiceObserver`.
- **Credit notes**, via `CreditService`.
- **AR aging and revenue reports**, which would each need to know about both.
- **PDF and IRD print templates**, which would drift.

The system already carries two storage-billing invoice types (`StorageInvoice`
and `StorageHandlingInvoice`). A third would compound that.

`pricing_mode` gives the same operator experience — its own menu entry, its own
URL, its own screen — while reporting can split the two whenever it wants to.

---

## 4. Schema

```php
// ..._add_pricing_mode_to_storage_handling_invoices.php
Schema::table('storage_handling_invoices', function (Blueprint $t) {
    $t->enum('pricing_mode', ['tariff', 'manual'])
      ->default('tariff')->after('bill_type')->index();

    // The free time the operator typed. Distinct from the line's
    // storage_free_days, which records what each line actually *consumed*
    // in this period — often 0 for a container that used its allowance
    // months ago. Two different facts, two columns.
    $t->unsignedSmallInteger('manual_free_days')->nullable()->after('pricing_mode');
});
```

`enum` rather than a boolean: a third pricing mode is easy to imagine
(contract-rate, promotional), and `is_manual` would have to be widened anyway.

Charge code constants, so the fact lives in one place:

```php
// App\Models\ChargeCode
public const DEFAULT_STORAGE  = 'STC';
public const DEFAULT_HANDLING = 'LOLO';
```

Both tariff Blades then read the constant instead of a literal. Three copies of
a business fact is how it silently drifts when someone renames a code.

---

## 5. Build order

Each phase is independently shippable, and the existing tariff flow is untouched
until Phase 4 (which only adds a badge).

### Phase 1 — Mode, permission, guard branch *(no UI)* — **done**

- The migration above; `ChargeCode` constants; both tariff views read them.
  `TariffChargeCodeBackfillSeeder` reads them too — it was the third copy.
- `pricing_mode` and `manual_free_days` on the model, cast and fillable, plus
  `isManualPricing()` and a mode label.
- `store()` branches: tariff mode keeps `guardHandlingRates()`; manual mode
  calls a new `guardManualRates()` — every chargeable line has a rate, and the
  charge codes the bill actually needs resolve. A blank on a line with nothing
  chargeable is stored as 0, since the columns are decimals.
- New permission `billing.storage-handling.manual`, added to `config/modules.php`
  so `PermissionSeeder` creates it.
- **Nothing user-visible.** Existing behaviour byte-identical: manual mode is
  opt-in on a field the current screen does not post.

**One deviation from the plan as written.** The plan said to grant the new
permission to every role that already holds `billing.storage-handling.create`.
That would include `billing_clerk`, and a permission every invoice-raiser holds
by default is not a control — it would mitigate nothing, while §9 lists "its own
permission" as the mitigation for manual pricing bypassing agreed commercial
terms. So: `administrator` and `billing_manager` receive it automatically
through their existing wildcards, and `billing_clerk` does not. Granting it to
clerks is one line in `RolePermissionSeeder`, commented in place.

### Phase 2 — The manual screen

- Route `manual/create` + `manual/preview`, same controller, `mode` default.
- Header adds: **Free Time (days)**, default 0.
- `preview()` in manual mode skips tariff resolution entirely and returns lines
  with rate `0`, charge codes already resolved, and the day arithmetic done
  against the header free days.
- **Rate matrix** above the lines: one row per equipment type × size present in
  the period, with storage / lift-off / lift-on inputs. Typing a rate fills
  every matching line.
- Per-line boxes seeded from the matrix, individually overridable; an overridden
  line is marked so it is visibly not following the matrix.
- Changing free time recalculates every line **and** the totals — each line
  against its own remaining balance, not a flat number.

### Phase 3 — Store

- `guardManualRates()`: a chargeable line with no rate is rejected, naming the
  containers. A **zero** rate is allowed but requires explicit confirmation —
  zero is usually a mistake and occasionally a deliberate goodwill line.
- Free days recomputed server-side from `manual_free_days`, never trusted from
  the browser. Same rule as preview, shared so the two cannot drift — the
  pattern `TariffRateGuard` already uses.
- `pricing_mode` and `manual_free_days` stamped on the header.

### Phase 4 — Visibility

- **Manual** badge on the invoice list and detail.
- `pricing_mode` filter on the list.
- Revenue reports can group by it.
- The customer-facing PDF is unchanged — it says nothing about pricing mode.

### Phase 5 — Edit a draft *(both modes)*

- `edit` / `update`, gated on `isDraft()` in the controller, not only in the UI.
- Re-uses the create screen with the invoice's saved values loaded, including
  the rate matrix reconstructed from the stored line rates.
- Lines replaced wholesale inside the existing transaction (§7.4).
- `pricing_mode` and `invoice_no` immutable.
- Same guard branch as `store()`.

### Phase 6 — Tests

**Unit** (no database — the arithmetic is the design):

- cumulative free days: gated in before the period consumes the allowance
- header free time changing moves each line by its own remaining balance
- a container gated in mid-period gets a partial allowance
- rate matrix fill vs per-line override precedence

**Feature:**

- a manual invoice saves with no tariff for that customer at all
- tariff mode still blocks when a rate is missing — the guard is not weakened
- GL posting and totals identical between a manual and a tariff invoice with
  the same numbers
- charge codes land as `STC` / `LOLO` with tax from their tax codes
- a missing or deactivated charge code blocks the save
- the manual permission is enforced

**Feature — editing:**

- a draft invoice can be edited and its totals move accordingly
- an **issued** invoice cannot be edited, by route as well as by button
- editing does not change `invoice_no`, and cannot change `pricing_mode`
- an edited invoice's lines are replaced, leaving none orphaned
- editing a tariff invoice still runs the tariff guard — edit does not become a
  back door to unguarded rates

---

## 6. The rate matrix, concretely

```
Equipment Type      Size   Storage/day   Lift Off   Lift On    Lines
────────────────────────────────────────────────────────────────────
20ft GP             20     [   250.00 ]  [ 1500 ]   [ 1500 ]   34
40ft HC             40     [   400.00 ]  [ 2200 ]   [ 2200 ]   12
40ft Reefer         40     [   950.00 ]  [ 3500 ]   [ 3500 ]    3
```

Rows are derived from what the period actually contains, so the operator is
never asked for a rate nobody will use. Typing in a cell fills the matching
lines below and recalculates totals immediately.

An operator who overrides one container sees that line flagged, so a later
reviewer can tell a deliberate exception from a typo.

---

## 7. Editing a saved invoice

Editing is allowed, and the boundary is **status, not pricing mode**.

### 7.1 Draft only — never once issued or posted to accounts

`markIssued()` does both things at once: it mints an IRD invoice number from the
statutory sequence and posts the invoice to the ledger. After that the document
has been numbered and the money has moved, so an edit would falsify a tax record
and leave the GL disagreeing with the invoice it came from.

```
draft      → editable
issued     → not editable — correct via credit note
paid       → not editable
cancelled  → not editable
```

`StorageHandlingInvoice::isDraft()` already exists and is the gate.

**One edge worth naming.** Issuing can succeed while GL posting fails — the
existing flow warns *"Issued, but not yet posted to the ledger"* and offers a
retry. That invoice is `issued` but **not** posted. It is still not editable:
the IRD number has been minted, which is the half that cannot be taken back.
Gating on `isDraft()` rather than on posting status handles this correctly,
which is the reason to gate on status rather than on whether a GL entry exists.

The UI must not offer an Edit button outside draft, **and** the controller must
enforce it independently — a hidden button is not a rule, and the route is
reachable by hand.

This is the same boundary `destroy()` already uses, so it reads as consistent
rather than as a special case for manual bills.

### 7.2 Applies to both modes

The existing module has no edit at all today: a wrong invoice is deleted and
raised again. Adding edit for manual bills only would be arbitrary — a
mis-keyed tariff invoice is just as worth correcting, and the delete-and-retype
workaround is what operators do now.

So `edit` / `update` are added for **both** modes. Tariff mode re-runs
`guardHandlingRates()` on save; manual mode re-runs `guardManualRates()`. Same
branch as `store()`.

### 7.3 What an edit may change

| Editable | Fixed |
| --- | --- |
| Free time, rates, notes, invoice date, currency, tax percentages | `invoice_no` |
| Which containers are on the bill (re-run the period load) | `pricing_mode` |
| | `ird_invoice_no` (null while draft anyway) |

`pricing_mode` is deliberately immutable. Switching a saved invoice between
tariff and manual would mean re-deriving every rate from a different source
mid-edit, and the resulting document would be hard to explain later. Delete and
re-raise instead — that is a rare, deliberate act.

### 7.4 Lines are replaced, not patched

An update deletes the invoice's lines and re-inserts them from the posted
payload, inside the existing transaction. The alternative — diffing lines by
container and patching — buys nothing here: a draft invoice has no external
references to its line ids, and patching would add a class of bug (orphaned or
duplicated lines) for no benefit.

### 7.5 Audit

`AuditObserver` already covers this model, so an update is recorded with its
diff. Worth confirming the diff is legible for a lines-replaced update rather
than a wall of noise — if it is not, log a summary (line count, totals before
and after) rather than every column of every line.

---

## 8. Risks

| Risk | Mitigation |
| --- | --- |
| Manual pricing bypasses agreed commercial terms | Its own permission; `pricing_mode` stamped on the header permanently; badge on list and detail |
| Edit becomes a route around the tariff guard | Edit re-runs the same guard as `store()`, per mode; `pricing_mode` cannot be changed by an edit |
| An issued or posted invoice is altered | Gated on `isDraft()` in the controller, not only in the UI; covers the issued-but-posting-failed case |
| A zero rate slips through as a typo | Allowed but requires explicit confirmation, never silent |
| Free days granted per period instead of cumulatively | Same arithmetic as the tariff flow, shared between preview and store |
| Charge code deleted or deactivated in the master | Save blocked, naming what to fix — never post with no revenue account |
| Browser-computed totals reaching the ledger | Everything recomputed server-side in `store()`; the browser only previews |
| Manual invoices distorting revenue reporting | `pricing_mode` is filterable and groupable |

---

## 9. Resolved

Nothing is outstanding. The three questions this plan opened were answered
before any code was written:

| Question | Answer |
| --- | --- |
| Should the customer PDF show pricing mode? | **No.** The customer sees agreed numbers; the method is internal. |
| Raise a manual bill where a valid tariff exists? | **Yes.** The tariff can be right for the customer and wrong for one container; the badge records it. |
| Edit after save? | **Yes, while draft.** Never once issued or posted to accounts — §7. |

Two things deliberately left out of scope, to be raised only if operators ask:

- **Changing `pricing_mode` on a saved invoice.** Delete and re-raise instead
  (§7.3) — switching mid-edit would re-derive every rate from a different source
  and produce a document nobody could explain later.
- **Editing an issued invoice.** That is what credit notes are for, and the
  system already has them.
