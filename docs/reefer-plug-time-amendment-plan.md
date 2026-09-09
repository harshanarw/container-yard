# Amending reefer plug times

Recording a plug-in or plug-out is currently a one-way door. This is the plan to
make it correctable, and to give the seventeen unbilled sessions a way back.

---

## 1. You are right: there is no way to amend

Four guards, and between them they close every path:

| Action | Guard | Consequence |
| --- | --- | --- |
| `plugIn` / `storePlugIn` | `if (! $plugSession->isPending())` | recording a plug-in moves the session to `active`, so the plug-in time can never be corrected |
| `plugOut` / `storePlugOut` | `if (! $plugSession->isActive())` | recording a plug-out moves it to `completed`, so **neither** time can be touched |

Nothing reaches a session in `completed`, `billed` or `not_plugged` at all. The
only way to change a plug time today is a direct database edit, which leaves no
audit trail and no validation.

**This is why the seventeen sessions cannot be recovered through the UI.** They
closed with no plug-in, and there is no screen that will accept one now.

---

## 2. Three of the four things this needs already exist

Worth checking before designing anything, because it changes the size of the
job considerably.

**The audit trail is already there.** `ReeferPlugSessionObserver` extends
`AuditObserver` and passes `properties: $diff` — old and new values — on every
update. An amendment will be recorded automatically, with the user, the
timestamp and both values, without a line of new audit code. It even
special-cases the plug-out transition already.

**The permission pattern is already there.** `yard.reefer` declares
`['view', 'plug-in', 'plug-out', 'temp-log']` and `ReeferController` gates each
action separately in its constructor. An `amend` action drops straight in.

**The billing lock is already there, and it is already correct.**
`ReeferBillingService::createInvoice()` moves sessions to `billed`, and
cancelling or deleting the invoice moves them back to `completed`. So `billed`
is a real lock with a real key: amendment refuses while a session is billed,
and the operator's route is to cancel the invoice first — which the module
already supports and already tells them about.

What does *not* exist is the amend action itself, its validation, and its
screen.

---

## 3. What may be amended, and when

| Status | Amendable | Why |
| --- | --- | --- |
| `pending` | nothing | no times recorded yet — use plug-in |
| `active` | plug-in | correcting the time the box went on power |
| `completed` | plug-in and plug-out | the ordinary correction case |
| **`not_plugged`** | **both, and it promotes back to `completed`** | **the path that recovers the backlog** |
| `billed` | **refused** | amending would contradict an issued invoice; cancel it first |

The `not_plugged` row is the one that matters most. Entering both times on a
session that was closed unplugged turns it back into a billable session — which
is exactly what the seventeen need, and what no amount of re-labelling could do.

---

## 4. Validation: the amendment has to be possible, not just well-formed

A correction screen that accepts anything is worse than no screen, because it
launders a guess into a charge. Five rules, and four of them come from facts
the system already holds:

1. **`plug_out_at` strictly after `plug_in_at`.** Equal times bill nothing and
   almost certainly mean a mis-key.
2. **Neither in the future.** Same rule and same reasoning as the gate
   timestamps.
3. **`plug_in_at` at or after the visit's `gate_in_time`.** A reefer cannot be
   plugged in before it arrives.
4. **`plug_out_at` at or before the visit's `gate_out_time`**, when the box has
   left. It cannot draw power after it goes.
5. **For a container still in the yard, `plug_out_at` at or before now.**

Rules 3 and 4 are the same family as the Gate Data Check rules, and they are
the ones that stop a backlog entry being fixed with dates that could not have
happened. The form should *show* the visit window rather than only reject
outside it — the operator needs to know the range before typing, not after.

---

## 5. A reason is required

Amending a chargeable quantity is not the same as recording one. The Gate Data
Check review takes a mandatory note for the same reason: whoever reads this in
six months needs to know why the number changed.

The reason goes into the audit description, so it sits beside the old and new
values the observer already captures.

---

## 6. The backlog, and one convenience that makes it tractable

Seventeen sessions, each needing a human decision about when the box was
actually on power. `reefer:unplugged-sessions` already prints the worklist.

For most of them the honest answer is likely "plugged for the whole stay" — a
laden reefer in the yard for thirty-five days was not being switched on and off.
So the amend form offers a **"plugged for the whole visit"** action that fills
gate-in and gate-out into the two fields, clearly labelled as an assumption
rather than a record.

That is a deliberate trade. It makes seventeen corrections quick, and it is
defensible for a laden reefer. It is *not* right for a box that was plugged
late or unplugged early, so it fills the form rather than submitting it: the
operator still confirms, and the reason field still records that this was an
assumption.

---

## 7. What this does not do

**It does not recover what was never recorded.** If nobody remembers when a box
was plugged in, this screen cannot invent it. The best available answer is the
visit window, and the audit trail will say that is what was entered and why.

**It does not fix the process.** Currently Active 0 and Billed 0 say the
plug-in screen has never been used. An amendment path makes the backlog
recoverable; it does not make anyone use the screen tomorrow. Two things would:
the warning already planned for the sessions list (§8), and asking the yard
whether the operators know the screen exists.

---

## 8. Phases

**Phase 1 — the amend action.** Route, controller action, permission
(`yard.reefer.amend`), the five validation rules, mandatory reason, and the
`not_plugged` → `completed` promotion. The audit comes free.

**Phase 2 — the screen.** An "Amend times" button on the session detail, the
form showing the visit window as the allowed range, and the "plugged for the
whole visit" fill.

**Phase 3 — stop it recurring.** A banner on the Reefer Plug Sessions list when
unbilled sessions are missing plug times, saying how many and linking to them.
The count is already computable — `status = completed AND plug_in_at IS NULL`
is the same query `reefer:unplugged-sessions` runs.

Phase 1 alone is enough to recover the seventeen through the API or tinker;
Phase 2 is what makes it a yard task rather than a developer one.

---

## 9. Tests

- A completed session's plug-in and plug-out can both be amended.
- An active session's plug-in can be amended, and its status is unchanged.
- A `not_plugged` session given both times becomes `completed`, and then
  appears in the billing preview — the whole point of the exercise.
- A `billed` session refuses, and names cancelling the invoice as the route.
- Each of the five validation rules rejects, by name.
- The old and new values reach the audit log, with the reason.
- A session amended after being previewed but before invoicing prices at the
  amended figure, not the original.
