# Gate Movements Search

One row per visit, over a date period, with both gates on the same line and
filters for customer, BL and vehicle.

**The short version: about 85% of this already exists, under a different name.**
The right move is to finish it rather than build a second one beside it.

---

## 1. What is already there

**Container Inquiry** (`reports` section, `container-inquiry.view`) already does
the core of this:

| Requirement | Container Inquiry today |
| --- | --- |
| one row per container, not per movement | yes — one row per gate-in |
| gate-in **and** gate-out on the same row | yes — `Gate In` and `Gate Out` columns |
| job number | yes — `Job No` and `Job Type` columns |
| date period | yes — `date_from` / `date_to` |
| customer | yes |
| BL number | yes (`bl_number`) |
| vessel / voyage | yes |
| seal, EIR ref | yes |
| don't run on open | yes — `$searched = $request->hasAny(...)` |
| export | yes |
| paginated | yes |

The pairing is `ContainerInquiryService::matchGateOutsForPage()`, which delegates
to `ContainerMrStatusService::pairGateOuts()` — the same matcher the M&R cycle,
`containers:fix-gate-custody` and the new stock report use.

So the requirement is right, the need is real, and the answer is *not* a new
report. Building one would mean a second query over the same table answering the
same question, and the two would drift — which is exactly how the reefer PDF
ended up with a different page reserve from its siblings, and how gate-out
custody drifted from the visit.

**Daily Movements is not the comparison.** It looks like a movement list, but
its filters are `export_status`, `codeco_exported_at` and `csv_exported_at`: it
is the **CODECO/EDI export queue**, and it shows one row per movement because
one movement is one EDI message. That is correct for what it does, and it should
not be reshaped into a search screen.

---

## 2. What is genuinely missing

Three things, and the second is the one that matters.

**1. No vehicle filter.** `gate_movements.vehicle_plate` and `driver_name` are
recorded at both gates and shown on the container timeline, but neither is
searchable. For "which boxes did truck ABC-1234 bring in last week" — a routine
question after a gate dispute or a damage claim — there is no way to ask.

**2. The date filter is containment, not overlap.**

```php
->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('gate_in_time', '>=', $filters['date_from']))
->when(!empty($filters['date_to']),   fn ($q) => $q->whereDate('gate_in_time', '<=', $filters['date_to']))
```

Both bounds test **`gate_in_time` only**. So a search for August returns
containers that *arrived* in August. A box that arrived in June and left in
August does not appear — even though its gate-out is the event the operator is
looking for.

This is the third time this exact mistake has turned up in this codebase: the
reefer bill excluded sessions crossing a period boundary, the Inventory report's
date filters mean "arrived between", and now this. The shape is always the same
— a period filter applied to one end of a two-ended thing.

**3. The row does not carry the movement detail.** Gate In and Gate Out show
times. Vehicle, driver, BL and days-in-yard live on the detail screen, so
answering "who carried it and on what BL" means opening each container.

---

## 3. Industry framing

What is being described is a **gate log** — the standard search screen in any
terminal operating system, and the one operations staff live in.

- **Its unit is the visit, not the event.** A move-level list is for EDI and for
  equipment-interchange settlement; a gate log is for "what happened to this
  box", and an operator reading one row wants both ends of the stay.
- **It is queried by whatever the asker happens to know** — container number, BL,
  vessel, truck, driver, job, date. A gate log with few filters gets replaced by
  someone exporting everything to Excel, which is how reconciliations start
  disagreeing.
- **Overlap is the norm.** "Movements between these dates" means any movement in
  the window, not visits contained by it. Every TOS gate log works this way, and
  it is also how the storage and electricity bills now select their periods.
- **It is evidentiary.** Damage claims, demurrage disputes and EIR queries are
  all settled from the gate log, which is why the vehicle and driver belong on
  the row rather than two clicks away.

Mapped to the request:

| Asked for | Standard name | Status |
| --- | --- | --- |
| one line per container with both gates | gate log / visit list | exists |
| job number on the row | job reference | exists |
| period search | movement window | **exists but containment, not overlap** |
| customer, BL | consignment filters | exists |
| vehicle number | haulier filter | **missing** |

---

## 4. Recommendation

**Extend Container Inquiry. Do not build a second report.**

Two things follow from that, and one of them is not code.

**Rename it.** "Container Inquiry" reads as a single-container lookup, which is
why the screen that already answers this question was not found. **Gate Movements
Search** — or "Gate Log" — describes what it does, and the name is the cheapest
part of this whole piece of work. The route, permission and service keep their
names; only the menu label, the page title and the breadcrumb change.

**Fix the date semantics**, which is the only change that alters what the screen
returns.

The alternative — a new report — would duplicate `ContainerInquiryService`,
`matchGateOutsForPage()`, the customer resolution and the export, and would put
a second answer to "what happened to this box" one menu item away from the
first.

---

## 5. Plan

**Phase 1 — overlap, not containment.** The date filter matches a visit when
*either* gate falls inside the window:

```
gate_in_time BETWEEN from AND to
   OR EXISTS (paired gate-out whose gate_out_time BETWEEN from AND to)
```

Implemented as a `whereExists` against the movements table so it stays one
query. Add a **Movement** filter — `Arrived` / `Departed` / `Either` (default) —
because "boxes that arrived in August" is still a real question and the current
behaviour should remain reachable rather than removed.

This is the phase that changes results, so it goes out on its own with tests.

**Phase 2 — the vehicle and driver filters. Built.** `vehicle_plate` and
`driver_name`, matched against **both** gates through the same correlated
subquery the date window uses, which is now extracted so the pairing rule has
one home.

The index note above was half right and worth correcting: an index on
`vehicle_plate` cannot help `LIKE '%…%'`, because a leading wildcard defeats it.
So the plate is matched as a **prefix** — which is how plates are typed, and how
`container_no` is already matched in the same search — and migration 000310
indexes it. `driver_name` stays a "contains" match, because a name is searched
by any part of it, and is deliberately left unindexed: an index it cannot use is
just a slower write.

**Phase 3 — the row.** Add Vehicle, Driver, BL No and Days In Yard as columns,
and Cargo Status. `MovementVisits` already returns `days` and `open` per
movement, so the day count is a column, not a calculation.

**Phase 4 — the name.** Menu label, page title, breadcrumb, and a one-line
pointer from Daily Movements saying it is the EDI export queue and naming where
to search instead.

**Phase 5 — the export**, styled like the stock workbook: header block naming
the period, the filters and the row count. Same reason — it gets sent to people.

---

## 6. Decisions

**1. Default period.** The screen currently runs nothing until asked, which is
right and should stay. But should the date fields pre-fill with, say, the last
30 days? Recommendation: **yes, pre-filled but not auto-run** — the same pattern
as the stock report, where the date defaults to yesterday and one click runs it.

**2. Repeat visits inside the window.** A container that came in twice during
August has two visits. One row each, or one row for the container?
Recommendation: **one row per visit**, which is what the screen does today. The
user's requirement — "same job will not repeat in two lines" — is about not
splitting *one* visit across two rows, and per-visit rows satisfy that. Two
genuine visits are two genuine rows, and collapsing them would lose a gate-out.

**3. Open visits.** A box still in the yard has no gate-out. Include it with a
blank Gate Out and an "In Yard" marker, rather than excluding it — a period
search that silently drops everything still on the ground is the same class of
defect as §2.

---

## 7. Tests

- A visit that arrived before the window and departed inside it appears — the
  §2 defect, pinned.
- A visit that arrived inside and departed after appears.
- A visit wholly outside does not.
- `Movement = Arrived` restores the old behaviour exactly.
- A container with two visits in the window yields two rows, each with its own
  gate-out.
- An open visit appears with a blank gate-out.
- Vehicle matches on a gate-in plate and on a gate-out plate.
- BL and customer filters narrow as before — a regression guard on the change.
- The export carries the same rows as the screen.
