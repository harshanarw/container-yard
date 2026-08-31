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

### Phase 2 — The manual screen — **done**

- Routes `manual/create` + `manual/preview`, same controller, `manual: true`
  passed through. Both carry the `.manual` permission on top of view/create, so
  the screen is gated as well as the save. Own menu entry under Billing.
- Header adds **Free Time (days)**, default 0.
- `preview()` in manual mode resolves no tariff at all — not "resolves one and
  ignores it" — and returns lines with rate `0`, the `STC` / `LOLO` charge codes
  and their tax rates already resolved, and no missing-rate flags.
- **Rate matrix** above the lines: one row per equipment type × size the period
  actually contains, with storage / lift-off / lift-on inputs. Typing a rate
  fills every matching line.
- Per-line boxes follow the matrix and are individually overridable; an
  overridden line is outlined so it is visibly an exception, and a blank one on a
  chargeable position is outlined in red. Clearing an override hands the line
  back to the matrix rather than blanking it.
- Changing free time recalculates every line **and** the totals, each line
  against its own remaining balance.
- Save is blocked, naming the containers, while any chargeable position has no
  rate — the same rule `guardManualRates()` enforces on the server.

**The arithmetic now lives in `App\Services\Billing\ManualPricing`** — free-day
consumption, per-portion tax, and the matrix key. Three parties compute these
numbers (preview, the browser as the operator types, and `store()` on save) and
they must agree, so the rules are in one class and the screen's JavaScript
mirrors it deliberately and says so. A cross-language harness checks the PHP and
the JavaScript against the same cases.

**Still trusted from the browser until Phase 3.** `store()` accepts the posted
free days and subtotals in manual mode. Recomputing them server-side means
re-deriving each container's elapsed days from its yard-storage record rather
than from the posted `days_before_period`, which is Phase 3's job; doing it
half-way — recomputing from a number the browser also supplied — would look like
a check without being one.

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

---

## 10. Container selection *(new requirement — plan only, not yet built)*

**Requirement.** The period load brings back every container the customer had in
the yard. Some of them do not belong on this bill. The operator needs to
uncheck them: excluded containers drop out of the totals immediately, are not
saved, and never appear on the invoice afterwards.

Selection is **screen state, not invoice data**. An invoice is its lines — a
container that was excluded simply has no line, which is why the view and print
screens need no change at all: they already read `$invoice->lines`.

### 10.1 Where the checkbox lives

Not in one table. Bill type decides which tables are drawn, and on a
**handling-only** bill the storage card is hidden entirely — a checkbox that
lived only there would be unreachable for exactly the bill type where the
operator most often wants to drop a stray movement.

So the checkbox appears in **every table where the line is drawn**, and all of
them are views of one flag on the line:

| Bill type | Storage table | Lift-off table | Lift-on table |
| --- | --- | --- | --- |
| Storage & Handling | every line | lines with a lift-off | lines with a lift-on |
| Storage only | every line | — | — |
| Handling only | *hidden* | lines with a lift-off | lines with a lift-on |

A container that appears in two tables has two checkboxes, and unticking either
unticks both — they are not two switches, they are two pictures of one switch.
The existing repaint loop already addresses cells by line index, so keeping them
in step is the same mechanism the rate boxes use.

Each table header carries a **select-all** box that toggles the lines in that
table. The count badge becomes `12 of 15 containers`, and the summary card's
Containers tile shows the selected count — the operator should never have to
count rows to know what they are about to save.

### 10.2 Excluded lines stay visible

Greyed, not hidden. Hiding a row would make it impossible to put back, and the
operator needs to see what they left off before they save. The row keeps its
rate boxes but shows its amount as `—`, so it reads as *not on this bill* rather
than as *zero*.

### 10.3 What changes

Entirely in `create.blade.php`, plus one line in the submit handler:

| Piece | Change |
| --- | --- |
| Line model | `_selected` on each line, defaulting to `true`. `_`-prefixed, so the existing strip already keeps it out of the request. |
| `recalcLine()` | Untouched. A line's own arithmetic does not depend on whether it is on the bill. |
| `recalcAll()` | Totals, subtotals and the storage/lift footers sum **selected** lines only. Checkbox states repainted alongside the rate boxes. |
| `manualBlockers()` | Skips deselected lines — an excluded container with no rate must not block a save it is not part of. |
| New blocker | Zero selected containers blocks the save: *"Select at least one container."* |
| Rate matrix | Row counts recomputed from selected lines; a combination with nothing selected greys out, since its rate boxes now feed nothing. |
| Submit | `previewLines.filter(selected)` and re-index, so `lines[0..n]` is contiguous. |
| Counts | `sumContainers`, `lineCount`, `handlingCount` all report selected-of-total. |

**Nothing changes on the server.** `store()` only ever sees the lines it is
posted, and `guardManualRates()` only guards those. The invoice, its GL posting,
its PDF and its IRD print are identical to one where those containers were never
in the yard.

### 10.4 Interaction with Phase 5 (edit a draft) — **confirmed**

This is the part that needs stating now, because it is easy to get wrong later.

Editing a draft **re-runs the period load**, which brings back the excluded
containers. They must come back **unticked** — the invoice's saved lines are the
selection. Restoring them ticked would silently re-add containers the operator
deliberately dropped, on an edit made for an unrelated reason.

So Phase 5's edit screen seeds `_selected` from whether a container has a saved
line, not from the default. An operator can then tick one back on, which is the
behaviour they will want.

### 10.5 Consequence worth knowing

A container dropped from March's invoice is not deferred — it is **not billed
for March at all**. April's invoice covers April, so those days are never
invoiced by anyone. That is the correct behaviour for the requirement as stated
(the operator is deciding this container should not be charged), but it is a
silent revenue leak if the exclusion was a mistake or a "bill it next month"
intention.

Two optional mitigations, neither required and neither assumed:

- **A note on the header.** The operator types why containers were excluded.
  Cheapest possible answer to "why wasn't ABC1234567 billed for March?" — and
  the header already has a `notes` field, so this costs nothing but a hint in
  the placeholder text.
- **An unbilled-containers report.** Containers in the yard during a period with
  no invoice line covering it. This is the real answer, and it is useful well
  beyond manual pricing — but it is its own piece of work, not part of this one.

### 10.6 Scope

**Manual mode only**, as asked. The same control would work unchanged in tariff
mode — it is one flag and one filter, and none of the logic is manual-specific —
so if operators want it there too it is a small follow-up rather than a rewrite.
It is not enabled there now because nobody asked for it, and a tariff invoice
that silently omits containers is a different conversation with an auditor than
a manual one that does.

### 10.7 Cover

**Client logic** (the existing node harness, which runs the shipped functions
verbatim):

- a deselected line contributes nothing to any total or subtotal
- a deselected line with a blank rate does not block the save
- a selected line with a blank rate still does
- deselecting every container blocks the save
- selecting a container back restores its contribution exactly

**Feature:**

- posting a subset saves only those lines, and the totals match the subset
- the saved invoice's detail view lists only the selected containers
- an invoice whose lines are a subset still posts to the ledger correctly

---

## 11. Never bill the same days twice *(new requirement — plan only, not yet built)*

**Requirement.** A container dropped from one invoice must come back on the next
one for the same period, and a container already billed must not. A new invoice
shows only what has not been invoiced yet.

### 11.1 What exists today: nothing

Raising a second invoice for the same customer and period produces a **complete
duplicate**. Every container returns, every lift event returns, and nothing
objects. Verified rather than assumed:

| Possible guard | Exists? |
| --- | --- |
| Filter in `preview()` / `store()` against existing invoice lines | **No** — customer + date overlap only |
| `is_billed` / `invoiced_at` / `billed_up_to` on `yard_storage` or `gate_movements` | **No** — no such column anywhere |
| Unique or overlap constraint on `storage_handling_invoices` | **No** — only `invoice_no` is unique |
| Duplicate-period warning in the controller or the screen | **No** |
| A test covering it | **No** |

`StorageHandlingInvoiceLine` appears in the controller exactly twice: the `use`
statement, and the `create()` inside `store()`. It is never read back.

**This is pre-existing in the tariff flow**, not something manual pricing
introduced. Two sibling modules already solve it — repair billing keeps a dedup
set of billed estimate line items and re-checks at save; reefer flips
`reefer_plug_sessions.status` to `'billed'` and back on cancel. So there is
precedent to follow rather than a pattern to invent.

The data needed is already stored. `storage_handling_invoice_lines` holds
`container_id`, `storage_from`, `storage_to`, `has_lift_off`, `has_lift_on`, and
the parent carries `status`. `YardController.php:1249` already runs this exact
shape of query to block deleting an invoiced gate movement.

### 11.2 It is two rules, not one

- **Lift events** are single events on a date — billed or not, a clean exclusion.
- **Storage is billed by day range**, and ranges overlap partially. If 1–15
  March was invoiced and the operator now raises 1–31 March, the container is
  neither billed nor unbilled.

**Decision (confirmed): bill the remaining days.** The container appears with a
16–31 March window, flagged so the operator can see why the window is short.
Excluding it outright would match the wording of the requirement but would leave
16–31 March invoiced by nobody — March's bill skipped them and April's covers
April.

A container whose entire window is already billed, with no unbilled lift event,
does not appear at all. That is the "show only un-billed containers" case.

### 11.3 Two sources, not one

The legacy `storage_invoice_details` table also carries `container_id`,
`from_date` and `to_date`, and `ContainerHireService::storageHasInvoices()`
already reads it. A customer billed through the old module before the switch
would otherwise be re-billed by the new one, so **both** tables are subtracted.
Both use the same status enum, so both count `draft`, `issued` and `paid`, and
neither counts `cancelled`.

Counting **draft** matters: two operators previewing the same period at once
must not both bill it. Cancelling an invoice releases its days again, which is
what makes cancel-and-re-raise still work.

### 11.4 Shape

```
App\Services\Billing\PriorBilling      — the queries: given a customer, a period
                                          and a set of containers, returns each
                                          container's already-billed intervals
                                          and lift events

App\Services\Billing\DateWindow        — pure interval arithmetic:
                                          merge(intervals)
                                          subtract(window, intervals) → remaining
```

`DateWindow` takes plain dates and returns plain dates — no model, no query — so
the arithmetic that decides what a customer is charged is testable as
arithmetic. Same split as `ManualPricing`, and for the same reason.

Intervals rather than a set of dates: a container in the yard for a year is one
pair, not 365 entries.

**Non-contiguous remainders are real.** Bill 10–20 March as a correction, then
raise 1–31 March, and what is left is 1–9 plus 21–31. A line has one
`storage_from` and one `storage_to`, so it cannot express two ranges. The line
therefore records the outer bounds it covers and `storage_total_days` as the
**count of unbilled days** — which is what is actually being charged for, and
already a different number from the span. The screen shows "9 of 31 days already
billed" so the arithmetic is visible rather than mysterious.

**Free days need no special handling.** They are consumed from the original
gate-in, so a window starting 16 March simply has more elapsed days behind it
and less allowance left. The existing rule gets this right by construction.

### 11.5 Where it plugs in

| Place | Change |
| --- | --- |
| `preview()` | Trim each line's window and drop already-billed lift events. A line with nothing left is not returned. |
| `store()` | **Re-resolve at save.** A preview opened before another operator saved is stale, and the browser's numbers are not evidence. Overlap found at save is rejected, naming the containers — the same shape as the repair module's save-time re-check. |
| Migration | `storage_handling_invoice_lines` currently has **no indexes at all**. The overlap lookup needs `['container_id', 'storage_from', 'storage_to']`. |
| Screen | Per line, "N of M days already billed" when trimmed. |

**Editing a draft (Phase 5) must exclude the invoice's own lines** from the
prior-billing set, or an edit would trim its own days away and leave nothing.
`RepairBillingController::update()` already does exactly this.

### 11.6 Scope: both modes

This is a correctness fix, not a manual-mode feature, and it is shared code.
Leaving the tariff flow — the one in daily use — able to bill the same days
twice, while closing the hole only in the newer module, is not defensible.

The one workflow this changes: deliberately re-invoicing days that were already
billed. The correct paths for that both still work — cancel and re-raise (a
cancelled invoice releases its days), or a credit note.

### 11.7 Cover

**Unit** (`DateWindow`, no database):

- a window with nothing billed comes back whole
- a fully billed window comes back empty
- a billed prefix advances the start; a billed suffix pulls the end back
- a billed middle leaves two ranges, and the day count reflects both
- touching and overlapping prior intervals merge rather than double-subtract
- a prior interval extending beyond the window on either side is clipped

**Feature:**

- billing the same customer and period twice returns nothing the second time
- billing 1–31 March after 1–15 March bills 16 days, not 31
- a cancelled invoice releases its days — the container comes back in full
- a **draft** invoice still reserves its days
- an already-billed lift-off is not billed again, while an unbilled lift-on on
  the same container still is
- a container dropped via the §10 checkbox returns in full on the next invoice
  for that period *(the requirement that started this)*
- a stale preview is rejected at save rather than double-billing
- editing a draft does not trim its own days away
