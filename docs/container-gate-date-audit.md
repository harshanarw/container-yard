# `containers.gate_in_date` / `gate_out_date` — who reads them, and what breaks

An audit before any further change. Nothing in this document has been altered;
it exists to decide what is safe to move and what is not.

**The headline: no money is computed from the container master.** Every charge
in the system reads `yard_storage`, or a snapshot captured on an issued invoice.
That was the risk worth checking before touching anything, and it is not there.

---

## 0. Which table owns these columns

Four tables carry a `gate_in_date`, and only one is in scope. Conflating them is
the easiest way to read this audit wrongly.

| Table | Columns | In scope? |
| --- | --- | --- |
| `containers` | `gate_in_date`, `gate_out_date` | **yes** — the projection under review |
| `yard_storage` | `gate_in_date`, `gate_out_date`, `effective_gate_in_date` | no — the stay's *own* period, and the billing source of truth |
| `storage_invoice_details` | `gate_in_date` | no — captured on an issued invoice |
| `storage_handling_invoice_lines` | `gate_in_date`, `gate_out_date` | no — captured on an issued invoice |

Both invoice-line copies are **historical records of what was billed** and must
never be recomputed. An invoice that changes when a gate time is corrected is
not an invoice.

`containers.gate_in_date` was declared `date` in migration 000002 and has never
been altered since, so it has never been able to hold a time of day.

---

## 1. Billing-affecting readers: none

Traced every path that produces a number a customer pays:

| Where | Reads | Verdict |
| --- | --- | --- |
| `StorageBillingController` 103–106, 163–179 | `$storage->billing_gate_in_date`, `$storage->gate_in_date`, `$storage->gate_out_date` | `yard_storage` |
| `StorageHandlingController` 159–162, 314–324 | same | `yard_storage` |
| `WeeklyRevenueReport` 293–322 | `$stay->…` | `yard_storage` |
| `CargoTransferService::closeOpenStorage` 291–308 | `$storage->…` | `yard_storage` |
| `ContainerHireService` 37–63, 143–162, 251–283 | `$storage->…`, `original_gate_in_date` | `yard_storage`, `container_hires` |
| `YardController` gate-out storage close 1078–1100 | `$storage->…` | `yard_storage` |
| `YardController` 2160–2177, 2425–2447 | `$storage->…` | `yard_storage` |
| `PriorBillingIndex` | `gate_in_date` as the free-day anchor | `yard_storage` |
| `ReportController::billingQuery` 456–464 | `YardStorage::…whereDate('gate_in_date')` | `yard_storage` |

The free-day anchor is `billing_gate_in_date` / `effective_gate_in_date` on
`yard_storage`, which deliberately differs from the physical arrival on a
resumed hire. The container master has no equivalent and is not consulted.

**Consequence:** moving display readers to the ledger cannot change an invoice.
That removes the reason to sequence this behind new billing tests.

---

## 2. Writers — keep as they are

These maintain the projection. They are not the problem; the problem is that
nothing *guarantees* they all fire.

| Where | Writes |
| --- | --- |
| `YardController` ~402 | gate-in sets `gate_in_date`, clears `gate_out_date` |
| `YardController` ~1148 | gate-out sets `gate_out_date`, status `released` |
| `YardController` ~1336 | gate-out delete clears `gate_out_date`, restores `in_yard` |
| `YardController` ~1821 | gate-time edit updates `gate_in_date` |
| `CargoTransferService` ~282 | transfer-out sets `gate_out_date` |
| `LessorOnHireService` ~124 | off-hire sets `gate_out_date` |
| `ResetTransactions` 168–169 | nulls both |

Seven write sites across four classes, with no database-level guarantee of
consistency. `containers:fix-gate-custody` and Gate Data Check exist because
they drift.

---

## 3. Display-only readers — safe to move

No arithmetic that leaves the screen, or arithmetic used only for a badge.

| Where | What it shows | Note |
| --- | --- | --- |
| `resources/views/containers/show.blade.php` 449–455 | both dates, plus `diffInDays(today())` | bypasses `DaysInYard` |
| `resources/views/containers/_form.blade.php` 453–455 | both dates | read-only panel |
| `resources/views/container-inquiry/show.blade.php` 125–134 | both dates | the *header* only; the cycle table already reads movements |
| `resources/views/container-inquiry/print.blade.php` 125–134 | both dates | same |
| `resources/views/yard/index.blade.php` 331, 373 | arrival + `diffInDays(today())` | bypasses `DaysInYard` |
| `resources/views/yard/gate.blade.php` 1050–1051 | "stayed N days" | **see §5** |
| `YardController` 274–275 | "already in yard (since …)" warning | |
| `YardController` 2228, 2347, 2521 | JSON for the gate screen | 2347 bypasses `DaysInYard` |
| `ContainerController` 282 | JSON | |
| `ContainerOcrController` 63 | "in yard since" on an OCR match | |
| `SurveyController` 80 | survey date fallback | |
| `FixStaleContainerConditionCommand` 80, 88 | guards a QC backdate, prints a column | compares against a `date`, so same-day cases are coarse |

---

## 4. Must keep reading the master

| Where | Why |
| --- | --- |
| `GateDataCheck` 155–156, 191 | its job is to *compare* master against ledger and report the disagreement. Moving it to the ledger would make it compare the ledger with itself and report nothing. |
| `StoreContainerRequest` / `UpdateContainerRequest` | the container edit form writes these columns directly |

---

## 5. Two defects found while auditing

Neither is in scope for a mechanical move, and both are worth recording.

**`yard/gate.blade.php` 1050–1051 mixes the two sources.** *(Fixed — step 2.)*

```php
$stayed = (int) $mv->gate_out_time->diffInDays($mv->container->gate_in_date);
```

A movement timestamp subtracted from a master date — one side from the ledger,
the other from the projection. If the master has drifted, this number is wrong
in a way neither source would produce alone. It also uses a bare `diffInDays()`,
so a reversed pair reads as a confident positive.

**`Container::getDaysInYardAttribute()` is dead code.**

```php
public function getDaysInYardAttribute(): int
{
    $start = $this->gate_in_date ?? now();
    $end   = $this->gate_out_date ?? now();
    return (int) $start->diffInDays($end);
}
```

Nothing calls `->days_in_yard` on a Container anywhere in `app/` or
`resources/`. It also defaults a missing arrival to `now()`, which silently
returns `0` where the honest answer is "unknown", and bypasses `DaysInYard`.
It should be deleted rather than migrated — a fifth definition of the day count
sitting unused is a trap for whoever reaches for it next.

---

## 6. Recommended sequence

**Step 1 — `DaysInYard` everywhere. Done.** Four hand-rolled counts on the
master now go through `DaysInYard::between()`:

- `containers/show.blade.php` — the Days in Yard field
- `yard/index.blade.php` — the coloured badge on the in-yard list
- `YardController::inYardSearch()` — the `days` key, which the gate screen's
  container picker renders
- `YardController::containerLookup()` — the `days_in_yard` key behind the
  gate-out form's badge. Only the **fallback** branch changed: with a storage
  record this deliberately counts from `billing_gate_in_date`, the free-day
  anchor, which on a resumed hire is earlier than the physical arrival. Two
  different quantities under one label, and that part is intentional.

`Container::getDaysInYardAttribute()` is deleted, with a note left in its place
so the next person reaching for the obvious name finds the reason instead of a
gap. It was uncalled and not in `$appends`, and disagreed with `DaysInYard`
twice — a bare `diffInDays()`, and a missing arrival defaulted to `now()`,
returning 0 where the answer is "no arrival to count from".

`yard/gate.blade.php` 1048–1051 was **not** touched, and is now the whole of
step 2. Routing its subtraction through `DaysInYard` would have corrected the
sign while leaving the real defect — one operand from the ledger, the other
from the projection — and left an adjacent line in the same `@php` block to be
edited again a commit later.

Covered by `tests/Feature/Yard/DaysInYardConsistencyTest.php`.

**Step 2 — the gate screen's mixed calculation. Done.**

Fixed by pairing the movement, not by patching the subtraction.
`ContainerVisitDates::visitsForMovements()` returns both ends of the visit each
movement belongs to, keyed under **both** the arrival and the departure, so the
two rows that describe one stay cannot disagree. The panel then asks
`DaysInYard::between()` and labels the result by whether the visit has closed.

Both branches were wrong, not just the one in §5:

- The **departure** branch mixed sources. For a container that had since
  returned, `containers.gate_in_date` held the *later* visit's arrival — so a
  March departure was measured against a September arrival and the badge read
  `195d stayed`.
- The **arrival** branch always counted to `now()` and always said "in yard", so
  a gate-in row for a box that left weeks ago kept accruing days and claimed it
  was still here. It now reads `5d stayed`.

A testing note worth keeping: the old departure output, `195d stayed`, *contains*
`5d stayed`. The first version of the test asserted the short form and would
have passed against the bug it was written for. The assertions are now anchored
on the badge's own boundaries (`>5d stayed<`).

**Step 3 — display readers to `ContainerVisitDates`.** The eleven sites in §3.
Mechanical, no billing exposure, and it buys real timestamps on screens that
currently show bare dates. Worth doing in two or three commits by area
(container screens, yard screens, JSON endpoints) rather than one sweep.

**Step 4 — decide what the master columns are *for*.** Once nothing reads them
for display, they are a cache with two remaining consumers: Gate Data Check,
which wants them precisely because they can be wrong, and the container edit
form. That is a reasonable place to stop. Dropping them would mean rewriting
seven write sites and the two form requests for no functional gain, and the
columns are useful as the thing the diagnostics check against.

**Not recommended:** touching `yard_storage`, `storage_invoice_details` or
`storage_handling_invoice_lines`. The first is the billing source of truth with
its own free-day anchor; the other two are captured history on issued documents.
