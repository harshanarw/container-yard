# Architecture principles: containers, visits and jobs

The rules the yard's workflow, data model and billing must follow. They are
business rules first; the code is measured against them, not the other way
round.

- **Rules 1–6** were set on 23 Nov 2026, and **Rule 7** (renumbering) was added
  the same day.
- The answers to decisions **D1–D15** refined Rules 1, 2, 4, 5 and 7 and added
  **Rule 8** (owner and custody).
- The answers to **D16–D30**, on 24 Nov 2026:
  - opened the gate-in Customer to any customer type;
  - refined Rules 7 and 8;
  - added **Rule 9** (cargo transfer: a yard box rented to the customer, the
    cargo leaving mostly by lorry).

Each decision and where it lands is in §6 of `docs/architecture-gap-plan.md`,
which also holds the gaps between these rules and the current system, and the
plan to close them.

Each rule is stated as given, then made precise as **invariants**: statements
that can be checked in code and in data, and that tests should pin.

---

## Vocabulary

| Term | Meaning |
| --- | --- |
| **Container master** | The box's permanent profile: what it is, not what it is doing. One record per box for its whole life, under whatever number it carries (Rule 7). It links to the box's history (visits, jobs, movements, number changes, M&R) but holds none of it. |
| **Customer (of a visit)** | The party recorded as *Customer* at the gate-in, and the visit's billing party. Normally the box's owner or operator (shipping line, NVOCC, container operator) or its agent, but any customer type may be (D16), e.g. a consignee bringing a box for a cargo transfer. |
| **Shipment details** | Consignee, shipper, vessel, voyage, B/L, DO expiry: facts about the cargo trip, recorded on the movement. The consignee and shipper are named there; they are not the visit's Customer. |
| **Owner / Principal** | The party that owns or operates the box, often overseas (profile, Rule 8). A customer record; its owner prefixes (e.g. `MSCU`, `MEDU`) identify it from the number. |
| **Local agent** | The principal's representative in the country (`customers.local_agent_id` on the principal). It may deal with the yard on the principal's behalf. |
| **Principal's side** | The principal or its local agent. |
| **Custody** | Whose box it is *in the yard* at a given moment: the holder of the deepest open job on the visit, otherwise the visit's Customer (Rule 8). |
| **Linked container** | A second box taking part in a main job that is not the box the job's visit began with, e.g. the yard box rented to the Customer in a cargo transfer (Rule 9). Both numbers are shown under the one main job. |
| **Visit** | One stay in the yard: from the gate-in that opens it to the final gate-out that ends it. A box has many visits over its life. |
| **Main job** | The job opened by the gate-in that starts a visit. It owns the visit. |
| **Sub job** | A job opened under another job for a process within the visit (lease-in, rental, …). Sub jobs nest. |
| **Job party** | `yard_jobs.customer_id`: who the job is with. On the main job, the **billing party** for the visit. |
| **Holder** | `yard_jobs.held_by_customer_id`: who has the box during the job, when that differs from the job party. |
| **Job rate** | A rate agreed for one job (storage, handling, hire), overriding the general and customer tariffs for that job only. |
| **Current / former number** | The container number the box carries now, and any number it carried before a renumbering (Rule 7). |

---

## Rule 1 — The party that brings the box in is the visit's billing party

> Workflows start when a container arrives and is gated in. The same container
> can be brought in by different parties over its life — not necessarily the
> owner: a temporary bearer, a consignee, or a party that rented the box. Any
> customer type may bring a box in, and that party is the main billing party
> until the life cycle completes with the final gate-out.

**Clarified (D10, 23 Nov 2026).** The party recorded as **Customer** at the
original gate-in is the billing party. In practice that is the box's owner or
operator: the shipping line or NVOCC. A box coming back empty after an import
consignee has cleared the cargo is still the line's box, so the **line** is the
Customer. The consignee is recorded under the *Import Shipment Details*, and the
shipper under the export details. They are facts about the trip, not the party
the visit belongs to.

**Clarified (D16, 24 Nov 2026).** **Any customer type may be the Customer**,
because the business has real cases where it is not the line. For example, a
consignee on the way to another yard leaves the box here for a few days. Or a
consignee brings a laden box, has the cargo transferred into a yard-owned box,
and takes the empty original to the owner's nominated yard (Rule 9). In those
cases the consignee *is* the Customer and the billing party, because it chose to
bring the box here.

**Invariants**

- **1.1** Every visit starts with a gate-in. There is no visit without one.
- **1.2** The gate-in Customer is chosen deliberately, from **any** active
  customer of any type (D16). Normally it is the principal's side; sometimes it
  is a consignee or another party bringing the box for its own purposes. Where
  the owner is known and the Customer is not on the principal's side, the clerk
  sees it as information (Rule 8.4). The Customer is never copied *to* the
  profile as the owner.
- **1.2a** The consignee and the shipper are recorded as shipment details on the
  movement. They never replace the Customer, and nothing bills them because
  they are named there.
- **1.3** The gate-in party is the main job's party and the billing party for the
  whole visit, unless a sub job (Rule 3) names a different party for part of it.
- **1.4** The billing party does not change because of anything recorded on the
  container master, and never reads its value from there.
- **1.5** Every billing party can be priced: a party with no tariff of its own is
  priced from the general tariff, never at zero by default.

## Rule 2 — Gate-in opens a job, chosen by job type

> At gate-in the job type is chosen from the nature of the business, and a job
> number is assigned from it. The job opened by the initial gate-in is the main
> job — except when a rented box that went out comes back in with its renter.

**Invariants**

- **2.1** A gate-in and its job are recorded together or not at all. No movement
  exists without a job.
- **2.2** The job number comes from the job type's sequence.
- **2.3** An initial gate-in opens a **main job** (no parent).
- **2.4** A box returning from a rental is not a new visit. Its arrival carries
  the rental's sub job, and opens no new main job and no new storage stay.
- **2.4a** *(Later phase, D28.)* A box brought in **to collect cargo** held under
  an open cargo-transfer job (Rule 9) joins that job. It opens no new main job.
- **2.5** A job number shows, at a glance, **whether it is a main job or a sub
  job**, and **what the sub job is for** (lease-in, rental, renumbering, …).
  A sub job's number also shows the main job it belongs to: `<main job
  no>/<purpose code><n>`, e.g. `CCD-ER-00571/LH1` (D7, D20). Main jobs keep
  today's format; existing sub jobs keep their numbers.

## Rule 3 — Main jobs divide into sub jobs, which can have their own party

> The main job can be divided into sub jobs, and a sub job can have a different
> billing party. For example, the yard takes the box on hire: a sub job is
> opened and stays open until the box is off-hired and handed back to the main
> job's party.
>
> 3.1 That sub job can open its own sub job when the box is rented out to
> another party. The renter may keep it in the yard for storage, or take it out
> and return it. This can happen many times. When the on-hire agreement ends,
> its sub job closes.
>
> 3.2 These sub jobs may need rates different from the general storage tariff,
> so the rates are entered when the sub job is created, according to its
> purpose.

**Invariants**

- **3.1** Sub jobs link to their parent through `parent_job_id`, and nest to any
  depth the business needs (today: stay → lease-in → rental). Every walk of the
  tree is depth-bounded and cycle-guarded.
- **3.2** Every process that changes who is billed or who holds the box is a sub
  job. No such process runs without one, including an internal one (the yard is
  then the party).
- **3.3** While a sub job is open, its party is billed for its period; the parent's
  party is not billed for the same days. When it closes, billing returns to the
  parent's party, with continuity (free days, anchors) preserved.
- **3.4** A rental may keep the box in the yard, take it out, or both, any number
  of times within one agreement. Physical movements are recorded on the rental's
  job; the agreement ends only when it is closed, not merely because the box came
  back.
- **3.5** A sub job may carry **job rates** for its purpose, entered at creation.
  Pricing for any job resolves as: job rate → party's tariff → general tariff.

## Rule 4 — The container master holds only what never changes between jobs

> The container master identifies the box: container number, equipment type,
> constant weight figures, ventilation and the like. Everything specific to a
> job is kept on the gate-in / job records and linked from there to every place
> that uses it — inquiry, billing and so on.

**Clarified (D1, 23 Nov 2026).** The master keeps **no** job-specific
information, not even a calculated summary. Status (in yard / out, in repair,
reserved, available), yard location and M&R status belong to the job they arise
in. Every process, M&R included, takes them from the job record. The master
links *to* the box's jobs, movements and number changes; nothing links back to
the master for job data.

**Invariants**

- **4.1** The master holds the profile only: current number (Rule 7), equipment
  type / size / type, plate weights (tare, max gross, max payload), ventilation,
  build, owner (Rule 8), CSC plate, and for yard-owned boxes their asset details
  (Rule 6).
- **4.2** Visit and job data live on the **job** (the main job for the visit, a
  sub job for its own part) and its **movements**. That covers cargo status,
  seal, condition, grade, visit Customer, dates, yard status, yard location,
  M&R status, PTI, booking reservation, export readiness and actual weights.
  Every reader takes them from there.
- **4.3** Nothing reads job data from the master, and nothing writes it there:
  no copy, no cached summary, no fallback.
- **4.4** A master record can exist without any visit and without a customer
  (a yard-owned box in its depot, a box known before it arrives).
- **4.5** Profile changes are corrections, audited with before and after values;
  equipment type is corrected through its own guarded flow.
- **4.6** The master's screen shows the profile, plus the box's history as
  links: visits with their job tree, movements, number changes, M&R records,
  profile corrections. *Is it in the yard, where, and in what state?* is
  answered from the open visit's job, if there is one.
- **4.7** M&R (survey, estimate, work order, repair invoice) belongs to a job,
  and takes the box's visit state from that job, never from the master.

## Rule 5 — A main job completes only after its sub jobs; each job is costed

> A main job can be completed only after all its sub jobs are completed. This is
> validated and tracked. Each job has its own costing.

**Invariants**

- **5.1** A job cannot be completed while any job beneath it is open.
- **5.2** A visit's life cycle ending (final gate-out) completes the main job when
  nothing beneath it is open, and otherwise marks it as waiting, visibly.
- **5.3** Completing or reopening a job is recorded (who, when) and reopening is
  refused once the job has been billed.
- **5.4** Every revenue and cost line is tagged to the job it belongs to, so each
  job has its own P&L and a parent shows the roll-up of its sub jobs.
- **5.5** A final gate-out is never blocked by open sub jobs (D5). It shows a
  **detailed warning** listing each open sub job (number, purpose, party,
  holder, opened on, last movement) before the clerk confirms.
- **5.6** Open sub jobs cannot be forgotten. A dashboard component lists, so
  that someone closes them deliberately (D3):
  - rentals whose box has come back while the agreement is still open;
  - lease-ins still open;
  - main jobs waiting on sub jobs;
  - cargo-transfer jobs with cargo still in a yard box (Rule 9);
  - renumberings requested but not applied (Rule 7).

## Rule 6 — Yard-owned and leased boxes are first-class

> Boxes the yard owns or leases must be entered separately. A container master
> record is created for them; a box on hire from another party should be
> recorded as a job, possibly with a gate-in movement. The yard can use its own
> and on-hired boxes for other purposes, such as renting for storage or renting
> out and back. Yard-owned boxes need a separate identification in the
> container master, with purchase details in the profile.

**Invariants**

- **6.1** Ownership is part of the profile: `owned` (yard asset), `leased` (held
  under an agreement), or third-party, in which case the Owner / Principal
  party says whose (Rule 8). It is set deliberately, never defaulted from the
  first gate-in.
- **6.2** A yard-owned box has asset details on its profile: purchase date, cost,
  currency, supplier, reference, asset number, disposal.
- **6.3** A box taken on hire from another party is recorded as a job — with a
  gate-in when it physically arrives, without one when it is already in the yard.
- **6.4** Yard-owned and on-hired boxes can be put to the same uses as any other:
  stored, rented in the yard, rented out and back, used for substitution — each
  as a job under Rule 3, including when the yard itself is the party.

## Rule 7 — A box is its record, not its number: numbers can change mid-visit

> A shipping line can have the yard change a container's number. The box may
> have come in for that purpose, or may already be in the yard on another
> party's visit (e.g. a consignee's). It arrived under the old number; after the
> work it carries the new one, stays in the yard, and leaves — for example with
> a shipper — under the new number, which is its official number from then on.
> Storage and the rest continue on the same job.

Approach agreed on 23 Nov 2026: the box keeps its master record and only its
number changes, with a history. A new master per number would split the visit,
and rewriting the numbers already recorded would falsify the gate-in and the
CODECO messages already sent. The plan is Phase 8 of `docs/architecture-gap-plan.md`.

**Invariants**

- **7.1** A box is identified by its master record (`container_id`). Every link,
  match, pairing and grouping uses it — never the number text.
- **7.2** The master holds the **current** number. Every change is recorded in a
  number history (old, new, effective time, job, requesting party, reference,
  user), never overwritten silently.
- **7.3** Records keep the number they were made under: the gate-in under the old
  number, the gate-out under the new. Screens, passes, invoices and exports show
  the current number and the former one where they differ.
- **7.4** A renumbering is a **sub job** of the current visit, with its own
  charge (D11). It creates no gate movement and does not interrupt the visit:
  the same main job, storage, plug sessions and sub jobs continue.
- **7.4a** Only the **principal's side** may request it: the principal itself,
  or its local agent (D17). The yard decides whether a request is legitimate.
  The request records **who asked** and **in what capacity** (*Principal* /
  *Local agent*), plus their reference.
- **7.4b** The box must be **in the yard in the principal's custody** (Rule 8):
  - the visit's Customer is on the principal's side;
  - no rental is open;
  - no lease-in is open (D19).

  Otherwise the request is refused, with the reason shown, e.g. "on lease-in to
  the yard under CCD-ER-00571/LH1 since 3 Oct; off-hire first", or "visit
  Customer is consignee ABC Traders, not MSC or its agent".
- **7.4c** Storage stays with the visit's Customer; the renumbering changes no
  billing of the visit. The renumbering itself is billed to the requester
  selected on the request, principal or local agent, through its billing party
  if one is set (D21).
- **7.5** A number typed anywhere (gate, inquiry, OCR, search) resolves through the
  history: a former number finds the box and says it was renumbered; a former
  number presented at gate-in is challenged, because it is most likely the same
  box still painted with its old number.
- **7.6** The number changes only through the renumbering flow. The master edit
  form may correct a typo only on a box that has never been gated in.
- **7.7** When the owner prefix changes, updating the owner on the profile is
  offered in the same step and confirmed by the user (D12). Renumbering is only
  for a box on an open visit (D13). A mistake is undone by a reversing history
  entry, allowed only until something is recorded under the new number (D14).
  No EDI message is sent for the change itself (D15).
- **7.8** Later, M&R can raise a renumbering as part of a repair (D11). It uses
  the same flow and the same history; it is not a second way to change the
  number.

## Rule 8 — The owner is profile; custody is the job's

> Added on 23 Nov 2026, from the answer to D10. Some actions belong only to the
> box's owner (renumbering first), and need the box to be in the yard in the
> owner's custody, not in a consignee's or a renter's.

**Invariants**

- **8.1** The **Owner / Principal** is a separate party on the profile
  (`containers.owner_customer_id`), apart from any visit's Customer (D18). The
  principal's customer code identifies it. Its **owner prefixes** (`MSCU`,
  `MEDU`, …) are kept on the principal's customer record, so a number's
  prefix proposes the owner.
- **8.1a** **First capture at gate-in:** for a box whose profile has no owner,
  the gate-in form has an optional *Owner / Principal* field. It is pre-filled
  from the prefix, else from the Customer when the Customer is a principal-type
  party, and the clerk can change or clear it. It is written to the profile only
  when the profile's owner is empty; a gate-in never overwrites an owner.
- **8.1b** After that the owner changes only on the Container Master, audited
  (before / after), or through a renumbering that changes the prefix (D12).
- **8.2** **Custody** is never stored on the master. It is read from the open
  visit: the holder of the deepest open job, otherwise that job's party,
  otherwise the visit's Customer (the existing `holdingParty()` rule, applied to
  jobs). Custody is the *principal's* when it falls on the principal or its
  local agent.
- **8.3** An owner-only action (renumbering; any later one) is allowed only when
  the requester is on the principal's side, the box is on an open visit, and
  custody is the principal's. A refusal always states which condition failed.
- **8.4** Where the owner is known, a gate-in whose Customer is not on the
  principal's side is shown to the clerk as information, not refused (D16).
  Rule 1 still decides who is billed.

## Rule 9 — A job can involve more than one container: cargo transfer

> Added on 24 Nov 2026 (D16), and refined the same day with the yard's practice
> (D22–D28):
>
> 1. A customer brings in a **laden** box, usually sealed. The gate-in job type
>    is **CT (Cargo Transfer)**.
> 2. The cargo is transferred, fully or partly, into a **yard box**: one the
>    yard owns, or one it has leased in from a line under an on-hire agreement.
>    The yard box is rented to the customer at rates agreed when the transfer
>    sub job is created.
> 3. The customer's box goes out **empty**, or **partly loaded** if only part of
>    the cargo was transferred.
> 4. The cargo then leaves the yard box in one of two ways:
>    - **by lorry** or other vehicles, in one or many trips (the usual case);
>    - **in the rented yard box itself:** the customer takes it out, clears the
>      cargo outside, and brings it back empty.
> 5. When all the cargo has gone, the rental closes and the job can be closed.
>
> Bringing in another container to collect the cargo is rare, and is a later
> phase (D28).

Two boxes, named by role:

- **Customer's box:** the box the customer brought in laden. Usually a shipping
  line's box carrying the customer's cargo. It is billed like any of the
  customer's boxes.
- **Yard box:** the box the cargo is moved into. It is yard-owned, or leased in
  (Rule 6), in which case the yard's own on-hire agreement with the line runs
  on beside the rental, untouched.

```
Main job CCD-CT-00012   Customer: ABC Traders                      (gate-in type CT)
  ├─ MSCU1234567  customer's box: in LADEN (seal) … out EMPTY, or PARTLY LOADED
  └─ CCD-CT-00012/RL1  cargo-transfer sub job = rental of CCDU7654321 (yard box)
        rates agreed here: storage, handling, transfer, reefer, days out
        cargo out by lorry (no charge, gate pass each): trip 1, trip 2 … last trip → box empty
          or: CCDU7654321 out LADEN (seal) → cleared outside → back EMPTY
        rental closed → yard box back to yard stock → main job can complete
```

**Invariants**

- **9.1** A cargo transfer is identified **at gate-in** by the one job type **CT
  (Cargo Transfer)** (D26). That gate-in opens the main job, with the customer
  bringing the box as Customer (Rule 1). The customer's box arrives laden,
  under the normal laden-gate-in seal rules.
- **9.2** Each yard box the cargo goes into opens its own **cargo-transfer sub
  job**: a rental of that box to the main job's Customer, who is renter, party
  and holder. The yard box becomes a **linked container** of the main job, so
  one job shows every container number involved. Cargo split across two yard
  boxes means two sub jobs, `/RL1` and `/RL2`.
- **9.3** **The rates are agreed when the sub job is created** and entered in
  its cargo-transfer section (D25): storage per day, handling (lift on / off),
  the transfer, reefer electricity, and days out of the yard.
  They stay editable until billed, and apply to **the rented yard box only**,
  for the whole period of the sub job (D27).
- **9.4** The customer's box, and any other box of the customer's in the yard, is
  priced by the **normal tariff** (Rule 3.5: party tariff → general tariff).
  Normal-tariff boxes and rented yard boxes appear on the **same storage
  invoice**. Each line is priced from its own source, and the invoice shows
  which one (D27).
- **9.5** The yard box keeps its own identity, profile, owner and its own stay
  or lease-in. The rental records the job it **draws the box from**. That job's
  storage is suspended while the rental is open and resumed when it closes
  (Rule 3.3). A lease-in cannot be off-hired while its box is rented out.
- **9.6** The customer's box leaves on the main job:
  - **empty, no seal required**, when all its cargo was transferred. Laden in,
    empty out is the expected shape of a CT job;
  - **laden (partly loaded)** when part of the cargo stays in it, under the
    normal laden seal rules: a seal, or a no-seal reason (the
    existing gate policy: LCL, customs exam, broken / missing, special
    equipment) (D29).

  Its departure cargo status comes from the transfer, not from its arrival.
- **9.7** **Cargo leaving by lorry** is a **cargo-out** on the sub job (D30):
  - the vehicle and driver details;
  - optionally, the cargo taken (description, quantity, weight, volume);
  - its own **cargo gate pass**.

  It is **not** a gate movement, because no container moves ("gate movements
  are physical"), and the yard does **not** charge for it: the customer
  handles the loading. Many cargo-outs are allowed. The one marked **last**
  records the yard box as empty.
- **9.8** **Cargo leaving in the rented yard box:** a rental departure, **laden**,
  under the normal laden seal rules, carrying the sub job. Its empty return carries the same sub
  job and hands the box back to the yard.
- **9.9** The rental closes when the yard box is empty (last cargo-out), or is
  back empty from outside. The yard box returns to yard stock, with its own
  stay resumed. Closing is deliberate (Rule 3.4), prompted by the open-sub-jobs
  dashboard.
- **9.10** The main job completes only when the customer's box has left and
  every cargo-transfer sub job is closed (Rule 5). Partial cargo-outs keep it
  open.
- **9.11** Each box's state is on its own movements and jobs, never on the
  master (Rule 4).
- **9.12** *(Later phase, D28.)* A container the customer brings in to collect
  the cargo joins the open CT job (Rule 2.4a), receives the cargo by a further
  transfer, and leaves laden.

---

## How to use this document

- Any change to gate, job, storage, hire or billing code states which invariant
  it serves, and carries a test that pins it (see `tests/README.md`).
- A change that cannot meet an invariant updates this document first, with the
  reason, rather than quietly departing from it.
