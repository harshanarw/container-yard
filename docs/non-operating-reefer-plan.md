# Non-Operating Reefers (NOR)

A reefer container carrying dry cargo with its machinery switched off. Today the
gate forces a Reefer Service Type on any laden reefer, creates a plug session,
and later demands a PTI — none of which apply.

---

## 1. The requirement is real, and it has a name

**NOR — Non-Operating Reefer.** Standard across the industry. Lines load dry
cargo into reefer boxes routinely:

- repositioning empty reefer equipment and filling it rather than shipping air
- dry capacity short on a sailing while reefer plugs sit idle
- a reefer whose machinery is defective, moved as dry until it reaches a repair
  facility

For that voyage the box is a dry container that happens to have a compressor
bolted to one end. No plug, no set point, no temperature log, and **no PTI** —
a pre-trip inspection tests refrigeration that is not going to be used.

So this is not a workaround for an awkward screen. It is a real operating state
the system has no way to record.

---

## 2. Your proposal, and the one thing I would change

> *"give a Cargo Type selection for Gate Movements — Dry, Reefer, Empty"*

The instinct is right: the missing information belongs on the **movement**, not
on the container, because the same box is a NOR this visit and an operating
reefer the next. And defaulting it from the job type is exactly right.

**The change I would make is to keep it off `cargo_status`.** `Dry / Reefer /
Empty` mixes two independent questions:

| Question | Where it lives today |
| --- | --- |
| Is the box loaded? | `cargo_status` — `laden` / `empty` |
| Is the reefer machinery in use? | **nowhere** |

Putting `Empty` in the same list as `Dry` and `Reefer` forces them into one
field, and two problems follow.

**They can disagree.** Nothing stops `cargo_status = 'laden'` with
`cargo_type = 'Empty'`. Every screen then has to decide which one it believes.

**It loses a real state.** An empty reefer can still be plugged — pre-cooling
before loading — and still needs a PTI before release for reefer cargo. Choosing
`Empty` would leave nowhere to say "empty, machinery running".

And `cargo_status` is load-bearing well beyond the gate: handling tariffs key on
it (`handling_tariff_rates.cargo_status`), so do storage invoice lines, and the
Weekly Performance report splits its whole grid on laden/empty. Anything that
muddies it reaches billing.

### The alternative: one field, one question

Add to `gate_movements`:

```php
$table->enum('reefer_mode', ['operating', 'non_operating'])->nullable();
```

| Value | Meaning |
| --- | --- |
| `null` | Not a reefer box — the question does not arise |
| `operating` | Machinery in use: service type, plug session, PTI all apply |
| `non_operating` | **NOR** — dry cargo in a reefer box, machinery off |

`cargo_status` keeps saying `laden` for a loaded NOR, which is true and keeps
billing and reporting correct.

**Default exactly as you described:** a laden reefer defaults to `operating`,
which is what happens today, and the operator switches it to NOR when that is
what arrived.

---

## 3. Every place "it's a reefer" changes behaviour

This is the dependency map. Four consequences today, and a NOR needs to opt out
of three of them.

| # | Where | Today | With NOR |
| --- | --- | --- | --- |
| 1 | `YardController:325` | Service type mandatory when laden + reefer | Only when `operating` |
| 2 | `YardController:546` | Plug session created when laden + reefer | Only when `operating` |
| 3 | `YardController:924` | Gate-out blocked without valid PTI on an export release | Not for a NOR release |
| 4 | `ContainerMrStatusService:255` | Rung 18 "PTI due / failed", lane REEFER | Not while the visit is a NOR |
| 5 | `ContainerMrStatusService:293` | `MODIFIER_PTI_EXPIRED` chip | Same |
| 6 | `ReeferBillingService` | Bills completed plug sessions | **No change needed** — no session, no bill |
| 7 | Storage & handling tariffs | Keyed on equipment type and `cargo_status` | **No change** — a NOR is still physically a reefer box and occupies the same slot |
| 8 | Weekly Performance report | Splits laden/empty from `cargo_status` | **No change** — the deliberate benefit of not touching `cargo_status` |

Items 6, 7 and 8 needing nothing is the payoff for putting this on its own axis.

### Item 3 deserves care

```php
$reeferRelease = $needsBooking || (bool) ($purpose?->reefer_applicable);
if ($container->isReefer() && $reeferRelease && ! $container->hasValidPti()) { … }
```

Half of this is already right. A NOR released under a plain, non-reefer purpose
never trips it, because `reefer_applicable` is false.

The half that is wrong is `$needsBooking` — **any** export release of a reefer
box demands a PTI, including a NOR exporting dry cargo. That is the case to fix,
and it should read the **release**, not the arrival: a box that came in as a NOR
but is now being released loaded with reefer cargo genuinely does need a PTI.

So the gate-out check consults the *gate-out's* `reefer_mode`, not the gate-in's.
Which means the field belongs on both directions, and the gate-out form has to
ask the same question.

### Item 4 is about not crying wolf

A NOR sitting in the yard currently reads "PTI due" on the M&R board. It isn't —
nobody is going to PTI a box that is leaving as dry cargo. Left alone, every NOR
adds noise to the one screen the M&R team works from.

But the PTI genuinely *is* stale for that container's next reefer cargo. The
honest treatment is to suppress the **rung** for the current visit while keeping
the container's PTI record accurate, so the moment it comes back as an operating
reefer the status reappears without anyone re-entering anything.

---

## 4. The screen

Not a new "Cargo Type" dropdown sitting alongside the existing Cargo Status —
two similar-looking lists is how they end up disagreeing. Instead, the question
appears only when it is a question.

The gate form already reveals `#reeferServiceBlock` when a reefer type is picked
and cargo is laden (`gate.blade.php:1903-1908`). The same block gains the choice
above the service type:

```
Reefer machinery
  (•) Operating          — plug session, service type and PTI apply
  ( ) Non-operating (NOR) — dry cargo in a reefer box, machinery off
```

Choosing NOR hides the service type entirely, which is exactly the behaviour you
described. The default is Operating, matching today.

**Wording matters here.** "NOR" is what the industry says and what the paperwork
will show, so the label carries both the term and its meaning — a clerk who has
not met the abbreviation should not have to guess.

---

## 5. Existing data

`reefer_mode` is null on every historical movement. Two ways to read a null on a
reefer box, and only one of them is safe:

- **Treat null as `operating`.** Every past reefer visit continues to behave as
  it does now. Nothing re-interprets, nothing changes on old records.
- Treat null as unknown and prompt — no. Thousands of rows nobody will revisit.

So: **null means operating for a reefer, and means nothing for a dry box.** A
backfill would be cosmetic and is not worth a migration.

The one consequence: the Gate Data Check and M&R screens will not retroactively
discover that some past visit was really a NOR. That is correct — nobody
recorded it, and guessing would be inventing history.

---

## 6. Phases

**Phase 1 — record it.** Migration, the gate-in form choice, and the two gate-in
rules (items 1 and 2). After this a NOR can be gated in without a service type
and without a plug session. This alone unblocks the yard.

**Phase 2 — stop the false demands.** The gate-out PTI gate (item 3, including
the `$needsBooking` gap) and the M&R rung (items 4 and 5). After this a NOR
neither blocks at the gate nor clutters the M&R board.

**Phase 3 — make it visible.** NOR shown on Container Inquiry, the M&R screens,
the gate pass and the Daily Movements export, so anyone asking "why is this
reefer not flagged for PTI?" can see the answer rather than wonder.

Phase 1 is the one with operational urgency. Phases 2 and 3 are what stop it
becoming a half-feature that records something nothing acts on.

---

## 7. Tests

- A laden reefer gated in as **operating** still demands a service type and still
  creates a plug session — today's behaviour, unchanged.
- A laden reefer gated in as **NOR** needs no service type and creates **no**
  plug session.
- A NOR's `cargo_status` is still `laden`, so handling tariffs and the Weekly
  Performance grid see it exactly as before. *This is the test that proves the
  axes stayed separate.*
- A NOR is not blocked at gate-out for a missing PTI, including on an export
  release.
- A container that came in as a NOR **but is being released with reefer cargo**
  is still blocked without a PTI — the gate-out reads its own movement's mode.
- A NOR does not appear on the M&R board as "PTI due".
- A dry box never shows the reefer question at all, and stores `null`.
- Historical movements with `reefer_mode = null` behave exactly as they do today.

---

## 8. Worth deciding before Phase 1

1. **Should an empty reefer be able to be `operating`?** Today no plug session is
   created for an empty reefer at all (`cargo_status === 'laden' && isReefer()`),
   so an empty box being pre-cooled is already unrecordable. That is a
   pre-existing gap this design leaves room to close, but closing it is a
   separate change and I would not fold it in.

2. **Does a NOR affect what you charge?** The box occupies the same slot and is
   lifted the same way, so storage and handling should be unchanged — but if the
   yard charges reefer boxes a different storage rate regardless of whether the
   machinery runs, that rate should keep applying, and it will, because tariffs
   key on equipment type rather than on this flag. Worth confirming that matches
   how the yard actually prices it.

3. **Does the gate pass need to print NOR?** Likely yes, so the driver and the
   line's paperwork agree with the system. Cheap to add in Phase 3 if wanted.
