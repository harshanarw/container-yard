# Gate time validation — stopping impossible timestamps at the door

Two rules the gate paths do not currently enforce:

1. A gate-in may not be in the future.
2. A gate-out may not be earlier than the gate-in it closes.

**A container gating in and out on the same date stays legal.** That is the
requirement this plan is most careful about, and §3 is entirely about not
breaking it.

---

## 1. Where the gaps are

Backdating is permission-gated: `$gateInTime` and `$gateOutTime` only differ from
`now()` when the user holds `yard.backdate` *and* supplies a value. So every gap
below is reachable only by that role — which narrows the blast radius, and means
none of these rules will ever fire for an ordinary gate clerk.

| # | Path | Line | Gap |
| --- | --- | --- | --- |
| 1 | Gate-in create | `YardController:343` | `gate_in_time` is `['nullable','string','max:20']`, then `Carbon::parse()`. No upper bound — a mistyped year puts an arrival in the future |
| 2 | Gate-out create | `YardController:846` | `$gateOutTime` is never compared with the visit's gate-in |
| 3 | **Movement edit** | `YardController:1536` and the `$isAdmin` rules above it | Both `gate_in_time` and `gate_out_time` are editable, with **no** ordering or future check of any kind |

**Gap 3 is the widest.** The create paths at least have a plausible timestamp to
start from; the edit screen can retroactively break a record that was consistent
when it was written, in either direction, with nothing to stop it.

### What already exists, and is good

`YardController:287-318` guards a *different* thing well: a proposed gate-in
falling inside a stay the container had not yet left. It compares on movement
timestamps rather than `yard_storage`'s date columns, precisely so that same-day
turnarounds survive — the comment quoted at the top of this document. The new
rules follow the same discipline and reuse the same shape.

There is also `SameDayTurnaroundTest`, including
`test_a_gate_in_backdated_before_the_same_day_gate_out_is_rejected`. These rules
must leave every one of its cases passing.

### Nothing legitimate needs a future gate-in

Expected arrivals live in `container_bookings`. `gate_movements` records what
actually happened at the gate, so a future timestamp there is always an error,
never a pre-advice.

---

## 2. The rules

### Rule A — a gate-in may not be in the future

Applies on create and on edit, and only when a value was typed. `now()` is never
in the future, so an ordinary gate-in is untouched.

**With a small grace.** The field is a free-text datetime the operator types, and
a workstation clock a minute or two ahead of the server would otherwise reject a
value that means "now". Five minutes, as a named constant, with the reasoning
beside it — not a magic number scattered through three call sites.

### Rule B — a gate-out may not precede its gate-in

On the gate-out path the comparison target is **already in hand**:
`YardController:860` resolves `$visitGateIn = $custody->latestGateIn($container)`
a dozen lines after `$gateOutTime`. The check costs no extra query; the custody
lookup just has to move above the timestamp, or the check below both.

On the edit path it needs the movement's own pairing:

- editing a **gate-out** → compare against `pairGateOuts()`'s gate-in for it
- editing a **gate-in** → compare against the gate-out that closed it, if any

Reusing `ContainerMrStatusService::pairGateOuts()` here for the same reason the
Daily Movements work did: one notion of which movements belong together.

---

## 3. Why same-day survives — and the trap in it

The comparison is `gate_out_time >= gate_in_time`, on **timestamps**, and the
`>=` is load-bearing in two separate ways.

**A same-day pair with times is obviously fine.** In at 08:00, out at 17:00 on
1 August: `17:00 >= 08:00`. No rule fires.

**A same-day pair *without* times is the trap.** Where a timestamp is recorded
date-only — an import, a data fix, an operator typing `2026-08-01` with no time —
both ends land on `00:00:00` and the two are **exactly equal**. A rule written as
"strictly after" would reject it, and would reject precisely the case this plan
was asked to protect. So:

> **Equality is allowed.** `>=`, never `>`. A container may arrive and leave at
> the same recorded instant, because that is what a date-only same-day
> turnaround looks like once it is stored.

**What is still rejected on the same date:** in at 14:00, out at 09:00. That is
not a same-day turnaround, it is a reversed one, and it is the case that produced
the phantom "3 days in yard" the audit started from.

The two rules also brace each other. Once a gate-in cannot be in the future, an
ordinary gate-out at `now()` cannot precede it, so Rule B only ever fires on a
deliberately typed gate-out time.

---

## 4. Where the code goes

A single private guard on `YardController`, called from all three paths, so the
message and the tolerance are defined once:

```php
private function gateTimeError(?Carbon $gateIn, ?Carbon $gateOut): ?string
```

Returning a sentence rather than throwing, matching the surrounding style —
`sealRequirementError()` and `validationResponse()` already work that way, and
these screens return field-scoped errors to an AJAX form rather than exceptions.

Errors attach to the field the operator typed in: `gate_in_time` on Rule A,
`gate_out_time` on Rule B, so the message lands under the right box.

Wording matters here more than usual, because the person reading it has the
container in front of them and a queue behind:

> *"The Gate-Out time (01 Aug 2026 09:00) is before this container's Gate-In
> (01 Aug 2026 14:00). A same-day turnaround is fine — check the time, not the
> date."*

Naming the same-day case in the message heads off the wrong correction.

---

## 5. Before any of this ships: how much existing data would fail?

This is the question that decides whether it is a validation change or a
data-cleanup exercise, and it must be answered on the live database first.

**Future gate-ins:**

```sql
SELECT COUNT(*) AS future_gate_ins
FROM gate_movements
WHERE movement_type = 'in' AND gate_in_time > NOW();
```

**Reversed pairs, job-linked** — the ones `pairGateOuts()` actually pairs, so the
ones that produce a wrong figure today:

```sql
SELECT COUNT(*) AS reversed_pairs
FROM gate_movements go
JOIN gate_movements gi
  ON gi.container_id = go.container_id
 AND gi.yard_job_id  = go.yard_job_id
 AND gi.movement_type = 'in'
WHERE go.movement_type = 'out'
  AND go.gate_out_time IS NOT NULL
  AND gi.gate_in_time  IS NOT NULL
  AND go.gate_out_time < gi.gate_in_time;
```

**Reversed pairs, chronological** — a rougher count, since the exact pairing is
the app's rather than SQL's:

```sql
SELECT COUNT(*) AS suspect_containers FROM (
  SELECT container_id
  FROM gate_movements
  GROUP BY container_id
  HAVING MIN(CASE WHEN movement_type='out' THEN gate_out_time END)
       < MIN(CASE WHEN movement_type='in'  THEN gate_in_time  END)
) x;
```

**If all three return 0** this is a small, safe change: add the rules, add the
tests, ship.

**If they return anything** the existing rows need a decision before the rules
land, because validation on write does not touch them — they will sit there
failing rules nothing re-checks, and the first person to edit one for an
unrelated reason will be blocked by an error about a field they did not touch.

### What the live database actually returned

Counts: **1**, **1**, **3**. Small enough that this is a validation change, not a
cleanup exercise. The rows, and what each one is:

**A future gate-in — movement 1467, `MEDU8724659`, REGENT OCEAN.**
Recorded 28 Aug 05:52 with a gate-in time of **7 September 11:41**. Its gate-out
(movement 1468) is dated 28 Aug 11:23 — *before* the arrival. So one typo
produced both defects at once, and **Rule A would have stopped it at entry**.
The intended date is the yard's to say; `2026-08-07 11:41` fits (a month typed as
09 for 08, giving a 21-day stay), while `2026-08-28` does not, since 11:41 would
still fall after the 11:23 gate-out.

**A reversed same-day pair — `TRHU4193252`, ABANS LOGISTICS, job 910.**
Gate-in 1 Sep **14:43**, gate-out 1 Sep **13:09**: out an hour and a half before
in, on the same date. This is exactly the case §3 distinguishes from a
turnaround, and **Rule B catches it**. The gate-in row was created first, so the
gate-out inherited job 910 from `latestGateIn()` and then recorded an earlier
time — one of the two clocks is wrong, and the yard knows which.

**Two containers missing an arrival, not reversed — `GESU6455892` and
`ONEU2681189`.** Each has a gate-out with no gate-in before it at all
(14 Jul 10:50 and 27 Jul 10:15). **Neither rule rejects these, correctly**: there
is nothing to compare a gate-out against, and this is the `released_no_movement`
case the M&R ladder already models. A missing record is a different defect from a
contradictory one.

Both of those containers also carry **two consecutive gate-ins with no gate-out
between them**, which the overlap guard at `YardController:287` would reject
today. They almost certainly predate it. Worth knowing that the shape cannot
recur, and worth not confusing it with what these rules address.

**So: two rows the new rules would have prevented, two containers with an
unrelated defect, and nothing that would block the change.**

---

## 6. What to do about existing bad rows

**Not a migration that rewrites them.** A gate movement is a record of what was
entered at the gate; silently moving a timestamp to satisfy a new rule destroys
the evidence that something went wrong, and the corrected value would be a guess.

Proposed instead, in order:

1. **Count them** (§5). If it is a handful, the yard corrects them through the
   edit screen, which is what it is for.
2. **A read-only command** — `php artisan gate:audit-times` — listing the
   offending movements with container number, dates and customer, so someone can
   work through them. Cheap, and useful again later.
3. **Rules apply to changed values only** on the edit path: if an operator edits
   a movement's remarks and its timestamps were already wrong, the save is not
   blocked. Only a *changed* timestamp is validated. This is what keeps the new
   rules from turning historic errors into a wall.

Point 3 is the one that makes shipping this safe regardless of what §5 returns,
and it is a small amount of extra care in the edit path.

---

## 7. Tests

**Rule A**

- A backdated gate-in in the past is accepted (the existing behaviour).
- One a day in the future is rejected, with the error on `gate_in_time`.
- One two minutes ahead is accepted — the clock-skew grace.
- A user without `yard.backdate` is unaffected, since their time is always `now()`.

**Rule B**

- **In at 08:00, out at 17:00 the same date: accepted.** The requirement.
- **In and out at the same instant: accepted.** The date-only same-day case.
- In at 14:00, out at 09:00 the same date: rejected.
- Out on the day before the gate-in: rejected.
- A gate-out for a container with no gate-in at all: not rejected by this rule —
  there is nothing to compare against, and it is a separate defect with its own
  M&R status.

**Edit path**

- Editing an unrelated field on a movement whose timestamps already violate the
  rules: saves. (Point 3 above — the one that makes this shippable.)
- Editing the timestamp itself into a violation: rejected.

**Regression**

- Every case in `SameDayTurnaroundTest` still passes, unchanged. If any needs
  editing to accommodate these rules, the rules are wrong.

---

## 8. Sequence

1. Run §5 on the live database. **Nothing is built until that comes back** — the
   answer changes whether step 3 is needed.
2. `gate:audit-times`, read-only.
3. Correct or accept the existing rows.
4. Add the rules, the guard, and the tests.
5. Deploy. No migration, no seeder, no data fix — validation only.

---

## 9. As built

Two guards on `YardController`, called from all three paths, with a single
`GATE_TIME_FUTURE_GRACE_MINUTES = 5`.

`gateOutOrderError()` compares with `>=` and returns null when either side is
missing — a departure with no arrival on record is a *missing* record, not a
contradictory one, and the rule stays silent rather than inventing an objection.
Both live containers in that state pass.

The gate-out path needed no extra query: `$custody->latestGateIn($container)`
already resolves the visit's arrival a dozen lines below the timestamp, and it is
the same gate-in the movement is about to be linked to.

The edit path validates **only a changed timestamp**, through `gateTimeChanged()`.
The form re-submits every field, so "supplied a gate time" is not the same
question as "changed it" — and with four known-bad rows on the live system,
someone will open one to fix a seal number long before anyone corrects a date.
It resolves the movement's counterpart through `MovementVisits`, which already
owns the one pairing rule; a fourth place deciding which movements belong
together would defeat the point.

Verified against the live rows: `MEDU8724659`'s 7 September arrival and
`TRHU4193252`'s 13:09-after-14:43 departure are both rejected, and the two
containers with a missing arrival are not.

