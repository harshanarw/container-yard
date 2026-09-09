# Billing reefer electricity by period

Today a reefer is billed once, when it comes off power. A box that has been
plugged in since March and is still running has produced no invoice at all.
This is the plan to bill the current period for containers still on power, and
to never bill the same day twice.

---

## 1. You are right, and it is worse than you said

The requirement is correct and it is standard practice. But while confirming it
I found a second defect of the same family, live right now, that has nothing to
do with open sessions.

`ReeferBillingService::preview()` filters the period like this:

```php
if ($periodFrom) $sessionsQuery->where('plug_in_at',  '>=', $periodFrom . ' 00:00:00');
if ($periodTo)   $sessionsQuery->where('plug_out_at', '<=', $periodTo   . ' 23:59:59');
```

That is **containment**, not overlap. A session must start *and* finish inside
the period to appear on the bill. So a reefer plugged in on 28 February and
unplugged on 3 March is excluded from February's invoice (it ends too late) and
from March's (it starts too early). It is billed by nobody, and nothing says so.

Every long-running session is already falling through that gap. The open-session
problem you describe and this one are the same problem — the module thinks a
charge belongs to a single invoice, when a charge belongs to *days*, and days
belong to periods.

---

## 2. What the industry does

This is **progressive billing** of a continuing service, and reefer power is the
textbook case: metered, ongoing, no natural end date until the box leaves.

- **Utilities and terminal operating systems** bill power on a cycle, not on
  completion. A container on plug for six months produces six invoices, each
  covering its own days, with the last one closing the session.
- **Revenue recognition** (IFRS 15 / ASC 606) recognises revenue *as the service
  is delivered*, not when it finishes. Holding six months of power off the
  ledger until plug-out overstates one month and understates five.
- **Cut-off** is the control that stops period-end distortion: a bill covers a
  closed window ending no later than today, and days after the cut-off belong to
  the next bill.
- **No double billing** is enforced by recording *what was billed*, not by
  flagging *what has been billed* — because a continuing service is never simply
  "billed" or "unbilled".

Mapping to your requirement:

| You said | Industry term | How it lands here |
| --- | --- | --- |
| bill plugged-in containers for the current period | progressive / cycle billing | interim lines, session stays on power |
| once a period is billed, skip it next time | billed-days ledger, cut-off | interval subtraction against prior lines |
| (implied) the last bill closes it | final invoice | closing line when plug-out falls in the period |

---

## 3. Where the current design breaks

Four defects, and they compound.

**1. Selection excludes open sessions.** `unbilled()` requires
`status = 'completed'` and both timestamps. A session on power is `active` with
`plug_out_at` null, so it is never selected.

**2. Selection uses containment, not overlap** (§1). Live now.

**3. `status = 'billed'` cannot express a partial charge.** It is a one-shot
flag on the session. A session billed for March and still running is not
"billed", and marking it so would remove it from every future invoice — turning
one bug into a worse one.

**4. A line records the session, not the billed window.**
`reefer_electricity_invoice_lines.plug_in_at` / `plug_out_at` hold the session's
own times. There is nowhere to record "this line charges 1–31 March of a session
that started in February", so nothing can be subtracted next month.

---

## 4. The yard already solved this once

This is the part that makes the job small. Storage & handling had exactly this
problem — an ongoing stay, billed monthly, mustn't double-bill — and it was
solved with three classes that have no framework in them:

- **`App\Services\Billing\DateWindow`** — inclusive interval arithmetic.
  `merge()`, `subtract($from, $to, $billed)`, `days()`, `span()`,
  `isFragmented()`. Date strings in, date strings out. Its own doc block states
  the case exactly: *"A box invoiced for 1–15 March and re-billed for 1–31 March
  is neither billed nor unbilled; what is owed is the sixteen days nobody has
  charged for yet."*
- **`App\Services\Billing\PriorBilling`** — reads already-invoiced windows out
  of the line table. `LIVE_STATUSES = ['draft','issued','paid']`, so a draft
  reserves its days and cancelling releases them.
- **`App\Services\Billing\ManualPricing::freeDaysInPeriod()`** — free time spent
  from the original gate-in rather than granted afresh each period. *"A box gated
  in on 1 January with five free days and billed for February gets none."*

`DateWindow` is generic and needs no change. Reefer should reuse it and mirror
`PriorBilling`, not grow a parallel mechanism. Two modules solving the same
problem two ways is how they drift.

---

## 5. The design

### 5.1 What a session owes in a period

For each session, three intervals and one subtraction:

```
service window  = [plug_in_at .. (plug_out_at ?? period_to)]
period window   = [period_from .. period_to]
candidate       = service window ∩ period window
billable        = DateWindow::subtract(candidate, already_billed_for_this_session)
```

`already_billed` comes from the line table, exactly as `PriorBilling` reads
storage. `days = DateWindow::days($billable)` is what the customer is charged
for; `span = DateWindow::span($billable)` is what the line records.

An open session is not a special case in this arithmetic — it simply has no end,
so the period end supplies one. That is the whole of the change in principle;
the rest is plumbing.

### 5.2 Selection

Replace `unbilled()` in `preview()` with a scope that selects sessions
**overlapping** the period:

- status in `active`, `completed`, `billed`
- `plug_in_at` is not null and `<= period_to`
- `plug_out_at` is null **or** `>= period_from`

`not_plugged` and `pending` stay excluded — no plug-in, nothing to charge.
`billed` is included because, under §5.4, it no longer means "never look again".

### 5.3 Free days, minimum charge, and the cut-off

**Free days** use `ManualPricing::freeDaysInPeriod($tariff->free_days,
$daysBeforePeriod, $daysThisPeriod)`, where `$daysBeforePeriod` is
`plug_in_at → period_from`. Without this a monthly-billed customer receives
their free days twelve times a year. The function is already written and tested.

**Minimum charge** needs your decision — see §9.

**Cut-off:** `period_to` may not be in the future. Billing power not yet consumed
is the one error this design could otherwise make silently. Same family of rule
as the amendment screen's "not in the future".

### 5.4 The status model changes

Stop using `status` as the billed marker. The invoice lines become the single
source of truth for what has been charged, as they are in storage.

| Session state | After an interim bill | After the closing bill |
| --- | --- | --- |
| `active` | stays `active` — the box is still on power | n/a |
| `completed` | stays `completed` if days remain | → `billed` when nothing is left |

`billed` therefore means *completed and fully invoiced*, and is derived after
each invoice rather than stamped blindly. Two consequences worth stating:

- `ReeferBillingController::cancel()` and `destroy()` no longer need to walk the
  sessions back to `completed`. Removing the lines releases the days by itself,
  because `PriorBilling` only counts lines on live invoices. The status reset
  stays only as a correction for sessions already fully closed.
- The **Ready to Bill** counter changes meaning: not "completed sessions" but
  "sessions with unbilled days". That is the number a supervisor actually wants,
  and it will finally be non-zero for boxes on power.

### 5.5 Schema

`reefer_electricity_invoice_lines` gains three columns, named to match
`storage_from` / `storage_to`:

| Column | Type | Why |
| --- | --- | --- |
| `billed_from` | `date` nullable | first day this line charges |
| `billed_to` | `date` nullable | last day this line charges |
| `is_interim` | `boolean` default false | the box was still on power at period end |

`plug_in_at` / `plug_out_at` keep their present meaning — the session's actual
times — so the invoice can still show "on power since 12 Feb" beside "charged
1–31 Mar". Plus the index that makes the subtraction cheap, mirroring
`shil_container_period_idx`:

```php
$table->index(['plug_session_id', 'billed_from', 'billed_to'], 'ref_line_session_period_idx');
```

**Backfill:** existing lines get `billed_from` / `billed_to` from their own
`plug_in_at` / `plug_out_at` dates, which is exactly the window they charged.
Without it, history counts as unbilled and the first new invoice re-bills
everything ever charged. This backfill is not optional and must run in the same
migration.

### 5.6 What the screens show

- **Preview**: a "Status" column reading *In progress* or *Closing*, and the
  charged window per line where it differs from the session's own times. A
  fragmented remainder (`DateWindow::isFragmented()`) is flagged — it means a
  hole was punched by an earlier correction, which is rare and worth seeing.
- **Session detail**: a small "Billing history" panel — which invoices have
  charged which days. Without it, "why is this box only being charged nine
  days?" has no answer on screen.
- **Invoice PDF**: the line shows the charged window. An interim line saying
  only "plugged in 12 Feb" against a March invoice invites a customer query.

---

## 6. Worked example

Reefer plugged in **12 February**, tariff LKR 1,500/day, 2 free days, still on
power. Monthly billing.

| Invoice | Period | Candidate | Already billed | Billable | Free | Charged | Amount |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Feb | 1–28 Feb | 12–28 Feb | — | 12–28 Feb | 2 | 15 | 22,500 |
| Mar | 1–31 Mar | 1–31 Mar | 12–28 Feb | 1–31 Mar | 0 | 31 | 46,500 |
| Apr | 1–30 Apr | 1–9 Apr¹ | 12 Feb–31 Mar | 1–9 Apr | 0 | 9 | 13,500 |

¹ plug-out on 9 April, so April's is the closing line, `is_interim` false, and
the session becomes `billed`.

Re-running March after the March invoice was raised yields **nothing** — every
day is already billed. That is your "skip the billed period", and it falls out
of the arithmetic rather than needing a flag.

Today, that same container produces **one** invoice, in April, for 57 days at
once — or none at all, if it is still on power at year end.

---

## 7. What this does *not* change

**PTI stays as it is.** PTI is hourly and short — a pre-trip inspection does not
span a billing cycle. `DateWindow` works in whole days, so interim billing is
scoped to daily/long-term tariffs. An hourly session left open across a period
end is *reported*, not silently billed at day resolution. If PTI ever needs
interim billing, that is an hour-resolution version of §5.1 and a separate job.

**It does not change what a day costs.** Rate resolution, currency conversion,
tax and the tariff guard are untouched.

**It does not bill a session with no plug-in.** `not_plugged` stays out. That is
the amendment screen's job, and it now exists.

---

## 8. Phases

**Phase 1 — the arithmetic, headless.** `ReeferPriorBilling` (mirroring
`PriorBilling`) and a `billableWindow()` on the service, both pure and tested
against `DateWindow`. No screen, no schema. Provable before anything moves.

**Phase 2 — schema and backfill.** The three columns, the index, and the
backfill of existing lines. Deployable on its own; nothing reads them yet.

**Phase 3 — selection and pricing.** Overlap selection, open sessions included,
free days via `ManualPricing`, the cut-off rule, the derived `billed` status,
and the cancel/delete release. This is the change that alters invoices.

**Phase 4 — the screens.** Preview columns, the billing-history panel, the PDF
window, and the Ready-to-Bill counter's new meaning.

Phases 1 and 2 are safe to ship ahead of a decision on §9; Phase 3 is where the
money moves and should go out as one piece.

---

## 9. Three decisions I need from you

**1. Minimum charge on interim lines.** The tariff has a `minimum_charge`. If a
container is on power for only two days of a month, should the monthly minimum
apply to that line, or only to the closing invoice for the session? My
recommendation: **apply it only on the closing line**, comparing against the
session's whole-life total, so a long stay is never charged the minimum twelve
times over. Say if your customer contracts read otherwise.

**2. Interim billing on or off by default.** Should the preview include open
sessions automatically, or behind a checkbox ("include containers still on
power")? Recommendation: **on by default with a visible count**, because the
whole point is that these are invisible today — but a checkbox is a one-line
change if you would rather introduce it gradually.

**3. Amending a session with billed days.** After Phase 3, an active session may
have March invoiced while April is still open. If someone amends the plug-in to
an earlier date, those extra days become billable and would appear on the next
invoice. Allow it with a warning on the amend screen ("31 days on this session
are already invoiced"), or refuse? Recommendation: **allow with the warning** —
the subtraction handles it correctly, and refusing would make a genuine
correction impossible on any long-running box.

---

## 10. Tests

- A session open across three periods bills each period once, and the totals sum
  to the whole stay.
- Re-running an already-billed period produces no line.
- Cancelling an invoice releases its days; re-raising bills them again.
- A draft invoice reserves its days against a second concurrent preview.
- A session spanning a period boundary appears on both bills — the §1 defect,
  pinned so it cannot come back.
- Free days are consumed once across periods, not granted per period.
- `period_to` in the future is rejected.
- The closing period sets `billed`; an interim period does not.
- A backfilled historical line is not re-billed.
- An amendment that extends a partly-billed session bills only the new days.
