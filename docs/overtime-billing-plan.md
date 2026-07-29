# Overtime (OT) Tariff, Masters & Receipt Module — Implementation Plan

Container-yard Overtime billing per the ACDO Sri Lanka guidelines and the
provided SRS: weekly working-hour master, holiday calendar (mercantile), effective
-dated OT tariff versions/rules, an Overtime Receipt module (per BL), and mandatory
OT-receipt validation at Gate-In outside normal hours.

---

## 1. How the requirement maps to the current system

| Area the module needs | In the system today | Verdict |
|---|---|---|
| Gate-in timestamp | `gate_movements.gate_in_time` (admin backdate via `yard.backdate`) | ✅ reuse |
| BL number at gate-in | `gate_movements.bl_number` — **free-text string, no BL master** | ⚠ use as string key |
| Customer / consignee | `customer_id` (owner/shipping line, FK), `transporter_id` (FK), `consignee` (free text) | ⚠ receipt needs its own payer FK |
| Working-hour calendar | **none** | ★ greenfield |
| Holiday / mercantile calendar | **none** | ★ greenfield |
| OT tariff versions/rules | **none** (only `OT`/`HOL` charge codes seeded) | ★ greenfield |
| OT receipt entity | **none** | ★ greenfield |
| Receipting (bank/cash received a/c) | `ReceiptController` + `bank_account_id`→`BankAccount`→GL, cash fallback `1011` | ✅ reuse pattern |
| Chart of accounts (income) | `Account` `classification='income'`, 4xxx range | ✅ add `4009 Overtime Revenue` |
| GL posting (direct) | `ReceiptPostingService` → `PostingEngine::createJournal/postJournal` | ✅ reuse pattern |
| Number sequences | `NumberSequenceService::generate('...')` | ✅ add `ot_receipt` (OTR) |
| QR verify | `Qr::svgDataUri()` + signed `documents.verify` (already has a `receipt` case) | ✅ add `ot-receipt` case |
| PDF | Barryvdh DomPDF, data-URI logo/QR (`isRemoteEnabled=false`) | ✅ reuse pattern |
| Mandatory ON/OFF toggle | `CompanySetting` boolean + `CompanySettingController` | ✅ add `require_ot_receipt` |
| Gate-in business block | `validationResponse()` → 422 JSON (seal-policy pattern) | ✅ reuse |
| Permissions | role/permission master + gates | ✅ seed `ot.*` |

**Net:** the *financial plumbing* (receipting, COA, posting, numbering, QR, PDF, settings, gate-in blocking) all exists and is reused. The *OT domain* (working hours, holidays, tariff versions/rules, the receipt, the resolver, the gate-in validator) is greenfield and built fresh.

## 2. World-standard alignment

The SRS matches standard Sri Lankan depot practice (ACDO) and is consistent with
international depot after-hours/OT surcharge models (per-service-window billing).
Sound design choices worth keeping:
- **Effective-dated tariff versions** with the applied rate **snapshotted onto the
  receipt** — never mutate historical receipts (matches how estimates/invoices
  already snapshot amounts here).
- **Service-window (slab) model** (A short / B extended-to-next-day) rather than
  per-hour — this is how ACDO bills, and it maps cleanly to a `valid_from/valid_to`
  window on the receipt.
- **Holiday/Sunday overrides weekday**, with the **05:00–08:00 gap deliberately
  unconfigured** (manual approval) — a safe default, not a guessed rate.

## 3. Key design decisions (recommended)

1. **Lightweight BL, not a full BL master.** The SRS models `bl_headers`/
   `bl_containers`, but the yard captures containers directly at gate-in with a
   free-text `bl_number`. Recommendation: the OT receipt carries `bl_number`
   (string) + `customer_id` (payer) + `expected_container_count`; **utilization is
   count-based**, and each OT gate-in links `gate_movements.ot_receipt_id` and
   increments `used_container_count`. This delivers the per-BL billing, the
   extension scenario, and quantity checks **without** building a BL-management
   subsystem. (A full BL master can be a later phase.)
2. **Receipt payer = a real Customer (the consignee).** The receipt's `customer_id`
   is picked from the customer master (the consignee/payer), independent of the
   gate-in shipping-line owner. The gate-in free-text `consignee` is unchanged.
3. **Income recognised on payment/confirm** — DR Bank/Cash, CR `4009 Overtime
   Revenue` (+ optional Output VAT), posted directly via `PostingEngine` like
   `ReceiptPostingService` (no AR invoice in between).
4. **Tariff rates never hard-coded** — seeded into `ot_tariff_versions` /
   `ot_tariff_rules`; the resolver reads active rules by effective date.

## 4. Data model (new tables)

- `working_hour_sets` (id, name, effective_from/to, status, created/approved_by).
- `weekly_working_hours` (set_id, day_of_week, is_regular_working_day,
  normal_start_time, normal_end_time, after_hours_policy, active).
- `holidays` (holiday_date, name, holiday_type, is_mercantile,
  working_hour_override, custom_start/end, ot_day_category_override, active, remarks).
- `ot_tariff_versions` (version_code, name, effective_from/to, currency,
  source_reference, approval_status, active, created/approved_by).
- `ot_tariff_rules` (tariff_version_id, rule_code, movement_type, day_category,
  period_code, display_name, start_time, end_time, ends_next_day, rate_amount,
  currency, charge_basis, allow_receipt_extension, billing_mode_on_extension,
  priority, active).
- `ot_receipts` (receipt_no, bl_number, customer_id, tariff_version_id,
  tariff_rule_id, operational_date, valid_from, valid_to, receipt_amount,
  tax_amount, total_amount, currency, expected_container_count,
  used_container_count, status, extension_of_receipt_id, billing_mode,
  bank_account_id, payment_method, journal_id, remarks, created/approved_by,
  paid_at, timestamps).
- Add `gate_movements.ot_receipt_id` (nullable FK) + `is_overtime` (bool) +
  `ot_override_reason` (nullable).
- Add `company_settings.require_ot_receipt` (bool, default false).

Audit reuses the existing audit-log infrastructure.

## 5. Services

- **`OvertimeRuleResolver`** — `resolveDayCategory(date)` (holiday→mercantile→
  Sunday→Saturday→weekday priority), `isWithinNormalHours(datetime)`,
  `getApplicableRules(datetime, movementType)`, `buildValidityWindow(rule,
  operationalDate)` (handles `ends_next_day` rollover; 00:00–05:00 maps to the
  previous operational day's B window; 05:00–08:00 → unconfigured).
- **`OtReceiptService`** — `quote(bl, date, ruleId, count)`, `generate(payload)`,
  `confirm/pay(receipt, bankAccount)` (posts GL), `generateExtension(original,
  newRule, billingMode)`, `cancel/void(reason)`, `markUtilized(receipt, gateMovement)`.
- **`GateInOvertimeValidator`** — `receiptRequired(gateInDatetime)` (setting +
  resolver), `validateSelectedReceipt(receiptNo, bl, gateInDatetime)` (BL match,
  time coverage, status, quantity, holiday category), `enforceOrThrow()`.
- **`OtReceiptPostingService`** — DR bank/cash, CR OT income (+VAT), via
  `PostingEngine`, mirroring `ReceiptPostingService`.

## 6. Masters, seeders & config (default records)

- **Working hours seed:** Mon–Fri 08:00–17:00 (regular), Sat 08:00–13:00
  (half-day), Sun closed. `after_hours_policy = OT_REQUIRED`.
- **Holiday seed:** a representative set of 2026 SL mercantile/poya holidays as
  examples (admin edits/imports the rest).
- **Tariff seed:** version `ACDO-OT-2026-04` (effective 2026-04-01, LKR) + 6 rules
  exactly per the circular: OT-WD-A 17:00–24:00 =10,000; OT-WD-B 17:00–05:00+1
  =15,000; OT-SAT-A 13:00–17:00 =12,000; OT-SAT-B 13:00–05:00+1 =22,000; OT-HOL-A
  08:00–17:00 =20,000; OT-HOL-B 08:00–05:00+1 =30,000. `charge_basis =
  PER_BL_RECEIPT`, `billing_mode_on_extension = FULL_NEW_CHARGE`.
- **COA:** add `4009 Overtime (OT) Revenue` (income, credit, parent 4000) to
  `DefaultCoaSeeder`; optional global `charge_revenue` mapping → 4009.
- **Number sequence:** add `ot_receipt` (prefix `OTR`, company prefix, pad 6).
- **Permissions:** seed `ot.settings.view/edit/approve`,
  `ot.receipt.generate/approve/cancel/override_amount`,
  `gatein.ot.select/override`, `ot.reports.view`.
- **Setting:** `require_ot_receipt` default **false** (opt-in, like the seal
  policy) — admin turns it on to enforce.

## 7. OT Receipt module (maps to your 7 points)

1. **Enter BL Number** → `bl_number` (with lookup of existing gate-ins/receipts for
   that BL).
2. **Enter date & time → applicable range** → resolver returns the day category and
   the A/B rule options; the user picks the slab (17:00–24:00 vs 17:00–05:00, etc.)
   and the system computes `valid_from/valid_to` and the rate.
3. **Pick the Customer (consignee)** → `customer_id` from the customer master
   (payer; may differ from the container owner/shipping line).
4. **Income account** → posts to `4009 Overtime Revenue` (added to COA).
5. **Received account (bank/cash)** → `bank_account_id` (reused receipting field;
   cash fallback `1011`).
6. **Special printout** → title **"OVERTIME RECEIPT"** (exact wording per your
   decision), own `OTR-…` sequence, BL number, valid period, QR verification via
   signed `documents.verify`.
7. **Link to Gate-In** when out-of-hours → `gate_movements.ot_receipt_id`, gated by
   the `require_ot_receipt` **ON/OFF** admin setting.

Lifecycle: DRAFT → GENERATED → (APPROVED) → PAID/ACTIVE → PARTIALLY_USED →
FULLY_USED / EXPIRED; CANCELLED / VOID. Only allowed statuses are selectable at
gate-in (policy-configurable; default: **PAID**).

## 8. Gate-In integration

On gate-in save, when `require_ot_receipt` is ON and the resolver says the
`gate_in_time` is OT: require `ot_receipt_no`; validate BL match, time coverage,
status, remaining quantity, and holiday category; block via `validationResponse()`
(422) with a gate-operator-friendly message; on success link the receipt and
increment utilization inside the existing gate-in `DB::transaction`. Authorized
users (`gatein.ot.override`) may override with a mandatory reason. A new
"OT Receipt" field (pick-from-list or scan) appears on the gate form, shown only
when the gate-in time is out-of-hours.

## 9. Reports

OT Receipt Register, Receipt Utilization, Expired/Partially-Used, Gate-In OT
Validation, Manual Override, OT Revenue by Rule, BL Pending OT.

## 10. Phased build (each phase independently testable)

1. **Masters + seeders** — migrations + models + seeders (working hours, holidays,
   tariff version/rules, COA 4009, OTR sequence, permissions, setting). Tests:
   seed integrity, tariff rate snapshot.
2. **`OvertimeRuleResolver`** (pure logic) — unit tests for TC-001…TC-011 day/
   time/holiday/rollover/gap resolution.
3. **OT Receipt module** — model, controller (index/create/quote/store/confirm/
   show/cancel/pdf/lookup), numbering, GL posting. Tests: generate, quote, pay→GL,
   extension, utilization, void.
4. **Admin UIs** — working-hour grid, holiday calendar, tariff version/rule
   screens, receipt generation/lookup.
5. **Gate-In integration** — validator + setting + gate-form field + 422 block +
   utilization link. Tests: TC-001…TC-015 gate-in scenarios.
6. **Printout/QR + reports.**

Tests trace to the SRS §18 matrix (TC-001…TC-015) plus resolver unit tests.

## 11. Decisions needed before build

- **BL model:** lightweight `bl_number` string + count utilization (recommended) vs
  full `bl_headers`/`bl_containers` master.
- **Gate-in allowed receipt status:** PAID only (strict, recommended) vs allow
  APPROVED/GENERATED (credit customers).
- **Extension billing mode default:** FULL_NEW_CHARGE (recommended) /
  DIFFERENCE_ONLY / MANUAL.
- **Receipt title wording** and whether VAT/SSCL applies to the OT charge.
