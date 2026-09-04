# Gate Data Check

One screen that finds gate movements with impossible dates, and links each one to
the screen that already fixes it.

---

## 1. What it is for

Four bad rows were found on the live system by hand-writing SQL. That does not
scale, and more importantly the person who can run SQL is not the person who
knows what the arrival time should have been. This puts the finding in front of
the people who can act on it.

**Scope is gate timestamps only.** Not M&R, not billing, not anything else. Three
checks, all of them problems we have actually seen.

---

## 2. The three checks

| Check | Means | Example from live data |
| --- | --- | --- |
| **Gate-in in the future** | Arrival dated after today — a typo | `MEDU8724659`, in dated 7 Sep, recorded 28 Aug |
| **Out before In** | Departure earlier than its arrival, *including same-date wrong times* | `TRHU4193252`, in 14:43 and out 13:09 on 1 Sep |
| **No gate-in recorded** | A departure with no arrival at all | `GESU6455892`, out 14 Jul, nothing before it |

A same-date turnaround with the times the right way round — in 08:00, out 17:00 —
is **not** a finding. Nor is a pair recorded date-only, where both ends store as
`00:00:00` and compare as equal. Those are ordinary yard work, and the check uses
the same `>=` rule the gate validation does, for the same reason.

---

## 3. The screen

```
Gate Data Check                              [From] [To] [Customer] [Apply]

⚠ 3 need attention

Container       Problem                Detail                              
MEDU8724659     Gate-in in the future  In: 07 Sep 2026 (today is 04 Sep)   [Fix]
TRHU4193252     Out before In          In 14:43, Out 13:09 on 01 Sep       [Fix]
GESU6455892     No gate-in recorded    Out 14 Jul, no arrival on record    [Reviewed]

✓ 2 reviewed and accepted                                          [show]
```

Under **Reports → Gate Data Check**, with its own permission
`reports.gate-check` so it can be given to the people who correct data without
handing them every report.

---

## 4. How things get fixed

**[Fix] opens the existing movement edit screen.** Nothing new is built for
correcting. That screen already syncs a corrected gate-in date onto `containers`
and `yard_storage` so billing stays right, and it already refuses a correction
that is still contradictory — the validation shipped alongside this work.

**[Reviewed]** is for findings with no correct answer. `GESU6455892`'s arrival
was never recorded; there is nothing to fix and nothing to invent. It takes a
short note, records who and when, and moves the row to the reviewed list.

Without that, the list can never reach zero, and a list that always shows red is
a list people stop opening.

---

## 5. Two things deliberately not built

**No "Fix All".** Correcting a gate-in changes `containers.gate_in_date` and
`yard_storage.gate_in_date`, which storage billing reads. A bulk fixer is a
button that can re-price a month of invoices without showing anyone what it
changed.

**No auto-correct.** `MEDU8724659` was probably meant to be 7 August — that is
an inference from a typo, not a fact. A gate movement records what happened at
the gate, and the system should not write guesses into it. A visible error beats
an invisible fabrication.

---

## 6. Pieces

| Piece | Note |
| --- | --- |
| `App\Services\Diagnostics\GateDataCheck` | The three checks. Reuses `ContainerMrStatusService::pairGateOuts()` for pairing rather than deciding again which movements belong together |
| `GateDataCheckController` | One `index`, one `review` |
| `gate_check_reviews` table | `gate_movement_id`, `check`, `note`, `reviewed_by`, `reviewed_at` |
| `reports.gate-check` permission | Added to `config/modules.php` |
| One view, one nav entry | Under Reports |

Findings are computed on demand, not stored. A finding that gets fixed should
simply stop appearing, and a stored copy would need invalidating every time a
movement changed.

The review note is keyed on the movement, so if that movement is later edited into
a different problem it shows up again — the note says "this particular finding was
accepted", not "ignore this row forever".

---

## 7. Tests

- Each check finds its own case and does not fire on the others.
- **A correct same-date turnaround is not a finding** — in 08:00, out 17:00.
- **A date-only same-date pair is not a finding** — both ends `00:00:00`.
- A reviewed finding leaves the open list and appears in the reviewed one.
- Editing a reviewed movement into a *different* problem surfaces it again.
- The screen is refused without `reports.gate-check`.
- The three checks together find exactly the four rows the live database has, given
  the same data.

---

## 8. Deployment

One migration (the reviews table), one seeder run for the new permission:

```bash
php artisan migrate
php artisan permissions:sync      # or db:seed --class=PermissionSeeder
```

Then grant `reports.gate-check` to whichever roles should see it. **Not**
`RolePermissionSeeder`, which uses `sync()` and would wipe role tuning done
through the UI.
