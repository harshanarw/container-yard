# Storage & Handling — Summary Print Format

**Requirement.** Some customers should not see the yard's unit rates or the tax
breakdown. They need a document that shows one tax-inclusive amount per line and
a single total at the bottom, with no VAT or SSCL figures anywhere. The existing
default and IRD formats stay exactly as they are. Start with the manual module.

---

## 1. What already exists

Three things make this small.

**The tax-inclusive amount is already stored.** `storage_handling_invoice_lines`
carries `line_grand_total` (added in migration 30) — `line_total + line_sscl +
line_vat`, the per-line amount with tax in it. The header carries `total_amount`.
So the numbers the new format needs are already on the invoice; nothing has to be
recomputed and **no schema change is required**.

**The two existing formats are separate and stay untouched:**

| Format | Action | Template | Shows |
| --- | --- | --- | --- |
| Default PDF | `pdf()` | `billing/storage-handling/pdf.blade.php` | Rate/Day, Rate/Unit, SSCL, VAT, Grand Total |
| IRD Tax Invoice | `irdPrint()` | `billing/ird-tax-invoice-pdf.blade.php` | Statutory layout, unit prices, VAT |

The IRD template is **shared with other billing modules** and must not be edited
for this.

**Invoices are not emailed by the system.** `NotificationService` posts internal
notifications only; the operator downloads the PDF and sends it themselves. So
there is one place to add the choice — the detail screen — not a send-flow to
thread it through.

---

## 2. What the format is *not*

This matters more than the layout.

A **tax invoice must show the tax charged** — that is what lets the customer
reclaim it. A document that hides VAT must therefore not present itself as one.
So the summary format:

- is titled distinctly (**Invoice Summary**, not "Tax Invoice"),
- **omits the IRD invoice number**, which belongs to the statutory document,
- carries a one-line note that a tax invoice is available on request.

The IRD format remains the statutory document and is unchanged. The summary is an
*additional* customer-facing copy, not a replacement — which is also exactly what
the requirement asks for.

---

## 3. Shape

```
                          INVOICE SUMMARY
  Invoice No. SH-2026-0417              Period  01 Mar 2026 – 31 Mar 2026
  Billed to   Bringer Lines             Date    31 Mar 2026

  #   Container        Description                              Amount (LKR)
  ─────────────────────────────────────────────────────────────────────────
  1   TCLU1234567      Storage & handling, 01–31 Mar                45,430.00
  2   MSKU7654321      Storage & handling, 05–31 Mar                38,120.00
  3   TGHU1112223      Handling                                      3,540.00
  ─────────────────────────────────────────────────────────────────────────
                                                    TOTAL      LKR 87,090.00

  Amounts are inclusive of all applicable taxes.
  A tax invoice is available on request.
```

One row per container, and the amount is that container's `line_grand_total`.

**One row per container rather than per charge type**, deliberately. The default
PDF splits into three tables (storage, lift-off, lift-on) because it is an
internal working document. Merging them here is the point: a single figure that
mixes storage days with lift events cannot be divided back into a rate.

---

## 4. The decision this turns on — **settled: A**

**Does the description carry quantities?**

If a line reads *"Storage, 31 days"* next to *45,430.00*, the customer divides and
has the daily rate — and the format has not actually hidden what it set out to
hide. Three options, and they trade transparency against opacity:

| | Description reads | Rate derivable? |
| --- | --- | --- |
| **A** | `Storage & handling, 01–31 Mar` | Not for a mixed line; roughly, for a storage-only container over a known period |
| **B** | `Storage & handling, 31 days` | Yes, on any storage-only container |
| **C** | `Services rendered` | No — but the customer cannot check anything either |

**A, confirmed.** It says what was charged for and over what period,
which is what a customer needs to accept an invoice, while a combined
storage-plus-handling figure resists being turned back into a rate. B gives the
rate away on the commonest case. C tends to generate the phone call the invoice
was supposed to prevent.

---

## 5. Build

| Piece | Change |
| --- | --- |
| Route | `GET /billing/storage-handling/{invoice}/summary-pdf` → `summaryPdf()` |
| Controller | New method, same `billing.storage-handling.pdf` permission — the format reveals *less*, so it needs no new grant |
| Template | New `billing/storage-handling/summary-pdf.blade.php` |
| Screen | A third button on the detail screen, beside Download PDF and IRD Tax Invoice |
| Schema | **None** |

**Scope: manual invoices only, initially, and enforced in the controller rather
than only by hiding the button** — a hidden button is not a rule and the route is
reachable by hand. Same principle as the draft-only edit gate.

Lines with nothing on them (no storage amount, no lift) are filtered out, as the
default PDF already does.

Worth saying plainly: gating on `pricing_mode` is a scope choice, not a design
one. Hiding rates is just as reasonable on a tariff invoice, and the code is
shared — extending it later is deleting one condition, not building it again.

---

## 6. Cover

**Feature:**

- the summary shows each container's tax-inclusive amount, and the header total
- it shows **no** rate anywhere — asserted by searching the output for the
  invoice's own `storage_daily_rate` and lift rates
- it shows **no** VAT or SSCL figure, and neither word appears
- it does **not** carry the IRD invoice number, and is not headed "Tax Invoice"
- the per-line amounts sum to the printed total
- the default PDF and the IRD print are byte-for-byte unchanged
- a tariff invoice is refused by route, not merely missing its button
- a container with nothing chargeable is omitted

**Worth pinning:** the sum of `line_grand_total` equals the header
`total_amount`. The summary shows both, so a customer can add up the column and
check the bottom line — if those ever disagreed, this is the format that would
expose it.

---

## 7. Optional, not assumed

- **A per-customer default.** A flag on the customer record — "always send the
  summary format" — so an operator cannot accidentally send the detailed one to a
  customer who should not see rates. Genuinely useful, and a small change; left
  out until asked for.
- **Extending to tariff invoices.** One condition, as above.
