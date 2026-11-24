# Architecture review: gaps against the principles, and the plan to close them

Reviewed against `docs/architecture-principles.md` (Rules 1–9) on 23–24 Nov
2026, at commit 21153c6. This document supersedes the separate visit-state /
seal plan discussed earlier: that work is Rule 4 here (Phases 1 and 3).

**Headline.** The hire work built the right *shape* — a job tree, two parties
per job, visit pairing, holder-based handling — and that part largely complies.
What is missing is the **life cycle and the money around it**: no main job has
ever been completed, job data still lives on the container master, 21 of 48
parties have no tariff, renters are never billed, and job costing sees almost
none of the revenue. Two requirements added during the review fit the
existing id-based design but need new flows. **Renumbering a container
mid-visit** (Rule 7, Phase 8) also exposes code that matches containers by
number text. **Cargo transfer** (Rule 9, Phase 9) needs the yard's box rented
to the customer and the cargo's way out recorded.

**Decisions.** All thirty decisions (D1–D30) are answered; §6 has each answer
and where it lands. The ones that changed the plan:

- **D1:** the master keeps no job information at all. Status, location and M&R
  status move to the job, which grows Phase 3.
- **D10, D16–D19, D21:**
  - the gate-in Customer is the billing party, of any customer type;
  - the consignee is a shipment detail unless it brings the box itself;
  - only the principal or its local agent may request a renumber, with the box
    in the principal's custody;
  - the requester is billed.

  These add **Rule 8** and **Phase 8b**.
- **D3, D5, D7, D9, D20:** a seal-change reason, a gate-out warning listing open
  sub jobs, an open-sub-jobs dashboard, and sub-job numbers such as
  `CCD-ER-00571/LH1` (Phases 1 and 2).
- **D11:** M&R-raised renumbering later (8h).
- **D22–D30:** cargo transfer (Rule 9, Phase 9):
  - one CT job type;
  - the yard box rented to the customer at rates agreed on the sub job;
  - the customer's box out empty (no seal) or partly loaded (seal);
  - the cargo out by lorry in uncharged trips, each with its own gate pass, or
    in the rented box;
  - the customer's own boxes on the normal tariff on the same invoice;
  - a collecting container later.

---

## 1. How this was checked

| Method | What it covered |
| --- | --- |
| **Code review** | gate-in / gate-out, job creation and status, lease-in, rental, storage tariff resolution, posting, container master, the hire plan's phases |
| **Reference data** | `abans_cyms_database` (copy of live, 1,930 containers, 3,760 movements, migrated to 000321), on MySQL 8.4, read-only |
| **Probe tests** | nine temporary PHPUnit feature tests driving the real HTTP routes and services, each asserting today's behaviour against one rule. All nine confirmed their gap (§5). Not committed: they assert the wrong behaviour; each becomes a real test in the phase that fixes it |
| **Browser (Laravel Dusk, Chrome 158)** | the existing browser suite against the 8.4 database: 11 passed, 2 failed — `ExampleTest` (the scaffold stub expecting "Laravel" on `/`) and `GateInSubmitTest::blank_vehicle` (HTTP 419, an expired CSRF token inside the test, not an app fault). Screenshots also showed the gate-in party field labelled *Customer / Owner* (Rule 1) |
| **Unit / feature suite** | 1,273 passing, 1 skipped on MySQL 8.4 — unchanged baseline |

---

## 2. What already complies

Worth keeping, and building on rather than replacing:

- **Gate-in opens a typed, numbered job** (`YardJob::generateJobNo($jobType)`),
  and a **hire return carries the rental's job** instead of opening a new main
  job or a new storage stay (Rule 2.4) — `YardController::gateIn`, `$isHireReturn`.
- **Sub jobs nest** through `parent_job_id`: stay → lease-in (`LESSOR_ONHIRE`)
  → rental (`CONTAINER_RELET`), with bounded, cycle-guarded walks (Rule 3.1).
- **Two parties per job**: `customer_id`, `billing_direction`,
  `held_by_customer_id` — the lease-in inverts direction correctly.
- **Storage suspension** under a lease or rental and **resumption with the
  original free-day anchor** at off-hire (Rule 3.3, in part).
- **Hire rate tiers** per agreement (`hire_rate_tiers`, `HireTierPricing`) —
  the mechanism Rule 3.5 needs, already built for hire.
- **Handling billed to the holder**, **visit customer from the root job**
  (`visitCustomerId()`), **visit pairing** in one place (`VisitPairing`).
- **Storage & Handling billing reads Laden/Empty from the arrival movement**,
  not the master — Rule 4 already, in billing.
- **A job P&L with sub-job roll-up** exists (`JobPnlService::computeWithSubJobs`).

---

## 3. Findings, by rule

Severity: **High** — wrong money, wrong records, or a rule with no
implementation; **Medium** — rule holds only partly or by convention;
**Low** — wording or hygiene.

### Rule 1 — the party that brings the box in is the billing party

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G1.1 | **No general storage tariff.** A tariff exists only per customer; a party without one gets `free_days 0`, `daily_rate 0` at gate-in, and its stay cannot be priced. | `YardController.php:569-581`, `StorageMasterHeader::getRateFor()` returns null. Data: **282 of 2,059 gate-ins (14%) from 21 of 48 parties** have no tariff. Probe R1. | High |
| G1.2 | The gate-in party is labelled **"Customer / Owner"**. The Customer is not the owner (Rules 1.2, 8.1): the label becomes *Customer*, and the owner gets its own field (Phase 8b). | Gate form, Dusk screenshot. | Low |
| G1.3 | The visit party is also written to `containers.customer_id`, and `visitCustomerId()` falls back to it. | `YardController.php:451`; `ContainerCustodyService:57-80`. Data: 12 in-yard boxes where master and arrival disagree. | Medium |
| G1.4 | ~~Any customer can be chosen as the gate-in Customer.~~ **Not a gap (D16: allow all).** `Customer::selectable()` excludes only the internal party, which is what the business wants. Hygiene only: give the 11 untyped parties a type, so that "principal's side" (Rule 8) can be recognised. | `Customer.php:154`. Data: 2,018 gate-ins by 44 *Shipping Line* parties, 30 by one *Local Agent*, **11 by parties with no customer type**. | Low |
| G1.5 | **The consignee is free text**, recorded on the gate-in under Import Shipment Details: **438 of 2,059 gate-ins (21%)**, B/L on 72. This matches the clarified Rule 1. Gap: it is not shown on the gate-out pairing or inquiry as the trip's consignee consistently. | `gate_movements.consignee` (migration 000122); `YardController.php:250, 525`. | Low |

The data agrees with the clarified Rule 1. The Customer at gate-in is a line or
its agent on 2,048 of 2,059 gate-ins; the other 11 are by parties with no
customer type. The consignee is a shipment detail.

### Rule 2 — gate-in opens a job

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G2.1 | **Job creation is not atomic with the gate-in.** The job is created after the movement and storage row, in a `try/catch` that only logs; a failure leaves a movement with no job. | `YardController.php:605-641`. Data: 0 gate-ins without a job, **9 gate-outs** without one. | Medium |
| G2.2 | Legacy `HIRE_RETURN_IN` and `OFFHIRE_IN` **main** jobs exist (2 each) — returns recorded before the rental gate flow existed opened new main jobs. | Reference data (`CCD-HR-00001`, 30 Sep 2026; `CCD-HR-00002`, 5 Oct 2026). Today's code attaches a return to the letting job (`YardController.php:609-616`). | Low |
| G2.3 | **A job number does not show main vs sub, or its parent.** Every job is `<prefix>-<type code>-<seq per type>` (`CCD-ER-00571`, `CCD-RL-00004`). The type code shows the purpose, but a rental's number says nothing about the visit it belongs to (Rule 2.5, D7). | `YardJob::generateJobNo()`, `YardJob.php:72-79`. Data: 2,064 main jobs, 11 sub jobs (`RL` ×10, `LH` ×1). | Medium |

### Rule 3 — sub jobs, their parties and their rates

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G3.1 | **A renter keeping the box in the yard is never billed for storage.** The rental opens a storage row with `daily_rate 0`, type `on_hire`; billing reads only `normal`/`resumed`. | `ContainerHireService.php:87-95`. Probe R3. Data: 10 `on_hire` rows, all zero-rated. | High |
| G3.2 | **The rental fee itself is not billed** — Phase 5 (`SHIRE`, sub-hire AR) is not built. A rental earns nothing today. | `docs/container-hire-rental-plan.md` Phase 5. | High |
| G3.3 | **No job rates.** A sub job cannot carry its own storage / handling rate; only hire agreements carry tiers. | `yard_jobs` has no rate fields; no job-rate table. | High |
| G3.4 | **A rental can run without a sub job**: an internal letting (no renter), or a missing `CONTAINER_RELET` job type, silently creates none. | `ContainerHireService.php:155-175`. Probe R3b. | Medium |
| G3.5 | **One rental = one round trip.** The renter's return (`HIRE_RETURN_IN`) closes the letting automatically, so a renter cannot take the box out, store it back, and take it out again under the same agreement. | `YardController.php:643-660`. D3: a return ends only the round trip. | Medium |
| G3.6 | Off-hire hands storage back to the original party correctly, but `resumed` rows store `daily_rate 0` (8 of 8); pricing works only because billing re-resolves the tariff at preview. | Reference data. | Low |

### Rule 4 — the container master holds only the profile

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G4.1 | **Gate-in writes visit data onto the master**: cargo status, seal, condition, grade, customer, dates, location. | `YardController.php:447-465`. Probe R4. | High |
| G4.2 | **Gate-out reads visit data from the master**: Laden/Empty for the seal rule, and cargo, condition, grade, location for the departure record. Root cause of the seal complaint. | `YardController.php:1221, 1254-1260`; lookup `:2669-2695`. | High |
| G4.3 | **Seal not carried to the gate-out**; clerks retype it. | 193 of 869 laden visits left with a different seal (105 differ only by an `I`/`IN` prefix). | High |
| G4.4 | **Movement edits don't reach the master**, so the two drift; after an Empty → Laden edit the gate-out refuses the seal. | 20 gate-in cargo edits in the audit log; 5 departures released Empty then hand-edited (28 Oct–2 Nov); 4 never corrected, so **CODECO told the line they left empty** (FFAU6736115, CRXU9897525, ONEU7537739, EGSU8156383). 2 in-yard boxes will hit it (OOLU8851724, OOLU6094534). | High |
| G4.5 | Other visit data on the master: `pti_status/pti_at` (`ReeferPtiController`), booking reservation (`BookingService`), `csc_plate_valid`; five lease columns duplicating `lessor_on_hires` and **empty on all 1,930 rows**. | Model `$fillable`; reference data. | Medium |
| G4.6 | Cargo transfer changes Laden/Empty on the master mid-visit with no movement; repair changes condition on the master only. | `CargoTransferService.php:110-113, 185-190`; `ContainerStatusService.php:55`. | Medium |
| G4.7 | **Profile edits are not audited** (only the equipment-type correction is). | `ContainerController` has no `AuditService` call. | Medium |
| G4.8 | Readers of master snapshot fields: Inventory, a `ReportController` export, `YardController::lookup()`, the Container Master panel. | `inventory.blade.php:314`, `ReportController.php:196`, `YardController.php:3013`, `_form.blade.php:451`. | Medium |
| G4.9 | **Status and yard location are kept on the master** (D1 puts them on the job). `containers.status` (`available` / `in_yard` / `in_repair` / `reserved` / `released`) and `location_zone/row/bay/tier` are written at gate-in. A **yard shift updates only the master** (and the slot table), not the movement or the job. | `YardController.php:459`; `StorageZoneController.php:217-222`. Status read in 7 files; location in 22 files and views. | High |
| G4.10 | **M&R status is a projection stored on the master.** `ContainerMrStatusService` is its single writer, which is good, but it writes to `containers.mr_status*`, and **28 files and views** read it there. The M&R documents are already job-linked: **estimates 7 of 7, work orders 1 of 1** carry `yard_job_id`. Damages hang off the inquiry; `gate_movements.survey_id` is never set (0 of 2,059). | `ContainerMrStatusService.php:378, 469`; reference data. | High |
| G4.11 | **The master's customer is stale visit data.** On **288 of 1,930 masters (15%)** `containers.customer_id` differs from the Customer of the box's latest movement. | Reference data. | Medium |
| G4.12 | **The master screen shows no history.** It has no number history (none exists yet) and no linked list of visits / job tree / M&R. Inquiry has some of this, but groups by number text (G7.2). | `containers/show.blade.php`. | Medium |

### Rule 5 — completion and costing

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G5.1 | **No main job has ever been completed.** The final gate-out never completes the stay's job. | Data: **none of 2,064 main jobs completed (2,063 open, 1 in progress); 1,674 belong to boxes that have left.** Probe R5b. | High |
| G5.2 | **A main job can be completed while its sub jobs are open** — the status update has no tree check. | `YardJobController::update`, `:93-111`. Probe R5a (completed over an open lease and rental). | High |
| G5.3 | Reopen is unrestricted, including after billing; no permission separates complete/reopen from view. | Same method. | Medium |
| G5.4 | **Job costing sees almost no revenue.** Storage & Handling, Storage-only and Reefer invoice lines have **no `yard_job_id`**, so their GL postings carry no job. | Schema: job links exist on general/repair/supplier lines only. Data: **2 of 23 income postings** carry a job. | High |
| G5.5 | **The gate-out says nothing about open sub jobs.** A final gate-out with a lease-in or rental still open passes with no warning (Rule 5.5). | `YardController::gateOut`; Probe R5b. | Medium |
| G5.6 | **No list of open sub jobs.** Nothing shows rentals whose box is back while the agreement stays open, or lease-ins left open (Rule 5.6). | Dashboard has no such component. | Medium |

### Rule 6 — yard-owned and leased boxes

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G6.1 | **A master cannot exist without a customer**: `containers.customer_id` is NOT NULL and required on the form. A yard-owned box needs a fictitious party. | Schema; `StoreContainerRequest:21`. Probe R6a. | High |
| G6.2 | **A master on its own claims to be in the yard**: `status` defaults to `in_yard`. | Schema default. Probe R6b. | Medium |
| G6.3 | **`category` (owned / leased) is a label only** — no business logic reads it. All 1,930 rows are `consignee`. | No reference outside the model and views. | Medium |
| G6.4 | **No asset details** for yard-owned boxes (purchase date, cost, supplier, asset no., disposal). | No such columns. | Medium |
| G6.5 | **A yard-owned box never gated in cannot be rented** — a rental needs an open stay or a lease. | `ContainerHireService::guardOnHire`. Probe R6c. | High |
| G6.6 | **A box arriving already on hire from a lessor has no screen.** `LessorOnHireService::onHire()` (gate-in + lease job) exists, but the screen only calls `onHireInYard()`. | `LessorOnHireController.php:65`. | Medium |
| G6.7 | **Internal use** (the yard as party: storing its own boxes, internal rental) — hire plan Phase 7, not built; tied to G3.4. | Hire plan. | Medium |

### Rule 7 — renumbering a container (new requirement)

The foundation is better than the requirement might suggest: the number is
unique on the master, every one of the 10 tables that copies it **also carries
`container_id`, filled on every existing row**, and visit pairing
(`VisitPairing`) and storage already key on the id. What is missing is the flow,
the history, and a consistent rule that the id — not the text — is the identity.

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G7.1 | **The number can already be changed freely** on the Container Master form: only a uniqueness check, no history, no audit, no job. Movements keep the old number, so the box splits in two everywhere that matches by text (G7.2). | `UpdateContainerRequest.php:19`, `ContainerController.php:236` | High |
| G7.2 | **Grouping by number text.** Container Inquiry and the M&R status service match a box's movements, surveys, estimates, work orders and invoices by `container_no`. A gate-in under the old number and a gate-out under the new would read as two boxes: one visit never closing, one departure with no arrival. | `ContainerInquiryService.php:271-330, 410-426`; `ContainerMrStatusService.php:668-689`. 17 files match by number text in all (custody / M&R / storage-custody repair commands, storage report, OCR, reefer, hire). | High |
| G7.3 | **No number history** and no former-number lookup: a typed old number is simply "not found". | No such table or resolver. | High |
| G7.4 | **No renumbering job type or charge.** | `YardJobTypeSeeder`, `ChargeCodeSeeder`. | Medium |
| G7.5 | **A former number at gate-in is not challenged.** Once the master carries the new number, the old one is free, so a gate-in under it would silently create a second master for what is probably the same box. | Unique index on current number only. | Medium |

Safe to build on: all existing rows in `gate_movements`, `inquiries`,
`estimates`, `work_orders`, Storage & Handling and reefer invoice lines carry
`container_id` (0 missing), and today every movement's number matches its
master's (0 differ).

### Rule 8 — owner and custody (added from D10)

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G8.1 | **The owner is almost never linked.** `containers.owner_customer_id` is set on **3 of 1,930** masters. `owner_code` (text) is filled on 1,869, but nothing maps an owner code or prefix to a customer. So "only the owner may request" cannot be checked today. | Reference data; `Container.php:303`. | High (blocks 8c) |
| G8.2 | **Custody is not resolved per job.** `GateMovement::holdingParty()` answers "who is at the gate" for a movement, but nothing answers "whose custody is the box in now" from the open visit's job tree. | `GateMovement.php`; no visit-level resolver. | Medium |
| G8.3 | **No owner check at gate-in.** A box whose known owner is line A can be gated in for line B with no warning (Rule 8.4). | `YardController::gateIn`. No case in the data: no gate-in disagrees with the 3 owners that are linked. | Low |
| G8.4 | **The principal ↔ agent link exists but is barely used.** `customers.local_agent_id` (the principal's local agent) is set on **2 of 55** customers; `billing_party_id` on 2, read only by repair billing. Nothing records owner prefixes per principal. | `Customer.php:55-62`; `CustomerController.php:105-106`; reference data. | Medium |
| G8.5 | **No Owner / Principal field at gate-in.** A new box's owner can be set only on the Container Master form afterwards, and nothing prompts for it (D18). | Gate form; `ContainerController.php:388`. | Medium |

### Rule 9 — cargo transfer: a second container, rented to the Customer (D16, D22, D23)

The physical steps are already built: the cargo-transfer module
(`CargoTransferController` / `CargoTransferService`) moves the cargo into a
yard-owned or on-hired box, sends the source box out empty, and bills the
substitute's storage and reefer to the customer. What does not match the
business is the job structure and the end of the job. **No transfer has ever
been recorded** (0 rows in `cargo_transfers`, 0 `CR` or `CT` jobs), so it can
be restructured without migrating data.

| # | Finding | Evidence | Sev |
| --- | --- | --- | --- |
| G9.1 | **Two job types for one gate purpose.** The module lists only `CARGO_RENTAL_IN` (short code **CR**) gate-ins. The business identifies the job as **CT**, a hand-made `CARGO_TRNS` type on live, set up as a gate-in type. A gate-in typed CT never reaches the transfer screen (D23 / D26). | `CargoTransferController.php:25, 45`; reference data. | High |
| G9.2 | **The yard's box is not rented.** The transfer opens no sub job and no rental: the substitute's storage, plug session and departure all go on the source box's main job. The customer's use of the yard box is not recorded as a rental, though that is what it is (D23). | `CargoTransferService.php:40, 62, 101, 129, 278`. | High |
| G9.3 | **The job ends too early or not at all.** `complete()` ends the transfer by gating the substitute out as **`STORAGE_OUT`, empty**, on the source's job. No laden rental departure, no return, and nothing ties the main job's end to the yard's box (Rule 9.6–9.10). | `CargoTransferService.php:160-216, 184`. | High |
| G9.4 | **The yard's box's own stay is ended, not suspended.** The transfer closes the substitute's open `normal` storage row (gate-out date = day before). If the box is later kept as empty stock, no stay is reopened, so its own visit loses its storage. | `CargoTransferService.php:91-94`. | Medium |
| G9.5 | **No linked-container concept.** The substitute is a column on `cargo_transfers`, so the job screen, inquiry and the master's history can't show "this job also used box X". | `cargo_transfers.substitute_container_id`. | Medium |
| G9.6 | **Laden in / empty out must not trip the Phase 1 rule.** Phase 1 makes the gate-out take cargo status and seal from the arrival. For CT the source box arrives laden and leaves empty with no seal. Today this works only because the service writes the departure directly, bypassing the gate form. | Phase 1 design; `gateContainerOut(..., 'CARGO_RENTAL_OUT', 'empty')`. | Medium |
| G9.7 | **Job state on the master:** the transfer writes `status` / `cargo_status` to both boxes' masters, and the guard reads the substitute's `status` (Rule 4). | `:110-112, 186-189, 237, 296-299`. | Medium |
| G9.8 | A yard-owned substitute must already be gated in. That is the same gap as G6.5, closed by Phase 7. | `guard()`, `:237`. | Low |
| G9.9 | *(Later phase, D28.)* **A collecting box cannot join an existing job.** Every gate-in opens a new main job, except a hire return. A box the consignee brings to collect the cargo would start a separate visit and job, splitting the customer's job in two (Rule 2.4a, 9.12). Rare in practice. | `YardController.php:609-641`. | Low |
| G9.10 | **One transfer per gate-in, one-way.** `cargo_transfers` holds a single source → substitute pair, and the guard refuses a second transfer for the same gate-in. There is no way to record a partial transfer, cargo split across two yard boxes, or (later) cargo moving on into a collecting box (Rule 9.2, 9.6). | `CargoTransferService.php:241` guard: "A cargo transfer already exists for this gate-in". | Medium |
| G9.11 | **Transfer rates are partial.** The module takes a substitute daily rate and a handling charge per transfer. There are no rates for days out of the yard, reefer or lifts, and nothing a later box or transfer could reuse (D25). | `CargoTransferService.php:56-59`; `cargo_transfers.handling_charge`. | Medium |
| G9.12 | **The storage invoice ignores the rented box's agreed rate.** When the customer has a storage tariff, Storage & Handling billing prices **every** storage row from that tariff and its default free days, and reads a row's own `daily_rate` / `free_days` only when the customer has no tariff. A yard box's cargo-transfer storage (agreed rate, 0 free days) would be billed at the customer's normal rate with free days (Rule 9.3–9.4). | `StorageHandlingController.php:447, 466-487`. | High |
| G9.13 | **No record of cargo leaving by lorry.** The module ends a transfer only by gating the yard box out or keeping it. There is no cargo-out, no partial delivery, no cargo gate pass, and no "last load, box now empty" (Rule 9.7). | No such table or screen; `CargoTransferService::complete()`. | High |
| G9.14 | **The customer's box can only leave empty.** The service always gates it out `empty`, so a partial transfer (part of the cargo stays in it) is recorded wrongly (Rule 9.6). | `CargoTransferService.php:140`. | Medium |

### Found on the way (separate fixes)

- Equipment-type correction: the billing check misses a separately billed
  lift-on (e.g. a renter's); the no-job fallback rewrites later visits'
  departures.
- Movement edit: ~~no no-seal-reason field~~ (fixed in Phase 1); cargo-status
  edits are not checked against billing.

---

## 4. The plan

Ordered by dependency and by what is hurting now. Each phase is independently
deployable, ships with tests that pin the invariants it serves, and has a
deploy note in `docs/deployment-seeders.md` (migration / seeder / permission /
manual step).

### Phase 1 — The seal complaint, first (Rule 4, part) — built 24 Nov 2026

The live pain, fixable without waiting for the rest.

- **`App\Support\ContainerVisitState`:** one resolver for what the box is
  carrying on its open visit, in this order:
  1. an in-yard event after the arrival (today a cargo transfer: the
     substitute is laden while it holds the cargo, empty once emptied and
     kept);
  2. the arrival movement (the visit's gate-in, or the return from a rental);
  3. the master, only when no arrival is recorded or it has no cargo status.

  A bulk variant is left until a list needs it (Phase 3).
- **Gate-out and both lookups read the resolver** for Laden / Empty, the seal
  rule and the departure's cargo status. The seal is pre-filled *from Gate In*
  and editable.
- **Condition, grade and location stay on the master in this phase.** Each is
  changed mid-visit only on the master today (repair marks the box sound, a
  yard shift moves it, the master form regrades it). Reading the arrival
  instead would lose those changes, so they move with their writers in
  Phase 3a / 3c.
- **Seal changed at gate-out (D9):** allowed, with a warning and a **mandatory
  reason**. It is enforced at the gate and on the gate-out edit screen,
  ignoring case and spaces. It is stored on the departure (`seal_change_reason`,
  migration 000322), given its own audit line, and printed on the gate pass.
- **Movement edit** takes a *No-seal reason* (from "found on the way"), so an
  Empty → Laden correction on an unsealed box is no longer a dead end.
- **Report** `movements:cargo-mismatch` (no `--fix`): departures whose
  Laden / Empty disagrees with their arrival, with the cargo-status edits from
  the audit trail, for the yard to correct on the edit screen. It skips boxes
  emptied or filled by a cargo transfer. **15 on the reference copy.**
- Tests: `tests/Feature/Yard/GateOutFromVisitTest.php`.
- *Deploy:* migration 000322, code; then read the report. See
  `docs/deployment-seeders.md`.

### Phase 2 — Job life cycle (Rules 2, 3.2, 5.1–5.3)

- **Gate-in and job in one transaction**; the gate-in fails rather than saving a
  movement without a job.
- **`JobLifecycleService`** as the single writer of job status:
  - complete — refused while any descendant is open (bounded walk);
  - final gate-out completes the main job, or sets a visible
    `awaiting_sub_jobs` state when something beneath it is open. The gate-out
    is never blocked (D5), but before confirming, the clerk sees a **detailed
    warning** listing each open sub job: number, purpose, party, holder,
    opened on, last movement, and a link to it;
  - closing the last sub job completes a waiting parent;
  - reopen — refused once billed; permissioned (`yard.jobs.complete`,
    `yard.jobs.reopen`).
- `YardJobController::update` and the hire services call it; no direct status
  writes remain.
- **Every rental has a sub job**: an internal letting runs with the yard's own
  contact as party (the pattern lease-in already uses); a missing job type is
  an error, not a silent skip.
- **Open sub jobs dashboard (D3):** a component, with its own permission, that
  lists:
  - rentals whose box has come back while the agreement is still open (days
    since return);
  - lease-ins still open;
  - main jobs `awaiting_sub_jobs`;
  - renumberings requested but not applied (once Phase 8 exists);
  - cargo-transfer jobs with cargo still in a yard box, with days since the
    last cargo-out (once Phase 9 exists).

  Each row links to the job and its close action. A renter's return ends only
  that round trip, never the agreement.
- **Job numbering (D7 / D20, agreed):** a sub job's number is visibly a sub
  job, names its purpose and shows its main job: `<main job no>/<purpose
  code><n>`, e.g. `CCD-ER-00571/LH1`, `CCD-ER-00571/RL1`, `CCD-ER-00571/RL2`,
  `CCD-ER-00571/RN1`. A `/` marks a sub job, and the code is the job type's
  short code. Main jobs keep today's format. The nesting (a rental under a
  lease-in) shows in the job tree on screen, not in the number. The 11
  existing sub jobs keep their numbers (already on invoices and AP).
- **Backfill** `jobs:close-ended-visits` — report by default: main jobs whose
  visit has ended and has nothing open beneath it; `--fix` completes them with
  the gate-out time. 1,674 expected on the reference copy.
- *Deploy:* migration (status value if a new enum), permissions sync + grant,
  backfill after reading the report.

### Phase 3 — Job state off the master (Rule 4, all of it; D1)

D1 widens this phase. The master keeps **nothing** job-specific, not even a
calculated summary. The main job (for the visit) and the sub jobs (for their
part) become the record of the box's state, and every process reads from them.

- **3a — The job holds the visit state.** The main job carries yard status,
  yard location, condition, grade, M&R status, PTI, booking reservation and
  export readiness. Sub jobs carry their own where it differs (e.g. a rental's
  holder). `ContainerVisitState` (Phase 1) reads them, and the master is no
  longer a fallback.
- **3b — M&R reads and writes the job.** `ContainerMrStatusService` stays the
  single writer, but projects onto the job instead of `containers.mr_status*`.
  The 28 readers move over: the M&R lanes, booking, inquiry, dashboard,
  reports. Estimates, work orders and repair invoices already carry
  `yard_job_id`. A survey or inquiry gets one too, so damages belong to a job.
- **3c — Location on the job.** Gate-in sets it, a yard shift
  (`StorageZoneController`) moves it on the job and in the slot table (audited
  as today), and gate-out clears it. Stock, the yard map and the gate-out
  lookup read the open job.
- **3d — Stop writing and reading the master.** Gate-in, gate-out, cargo
  transfer, repair status, PTI and booking write only the job and its
  movements. Every reader in G4.8, G4.9 and G4.10 moves to the job. `in yard?`
  becomes "has an open visit", never `containers.status`.
- **3e — The master shows the box's history (Rule 4.6).** The screen shows the
  profile, a *Current visit* panel read from the open job, and links to visits
  with their job trees, movements, number changes, M&R and profile corrections.
  Audit every profile edit (before / after).
- **3f — Clear, then drop.** Make `customer_id` nullable and clear it with the
  other visit columns, `status` included; drop them a release later. Drop the
  five empty lease columns now.
- *Deploy:* migrations (job state columns or table, `yard_job_id` on survey /
  inquiry, column clearing), a backfill from each open visit's movements
  (report first), then code. Do it in slices, with each slice's readers moved
  before its writes stop.

### Phase 4 — Pricing every party, and job rates (Rules 1.5, 3.5)

- **General tariff** (D4: storage **and** handling): a tariff with no customer, used
  when a party has none. Resolution everywhere: **job rate → party tariff →
  general tariff**; the existing guard keeps reporting what is still unpriced.
- **Storage invoice prices each row from its own source:** the row's job rate,
  else the party's tariff, else the general tariff. Today a customer tariff
  overrides every row (G9.12). Each line shows its rate source.
- **`job_rates`**: per job, per charge (storage, handling, washing, hire), with
  free days and validity; entered when a lease-in, rental, cargo-transfer
  (Phase 9) or other sub job is created, editable until billed.
- *Deploy:* migrations; manual step — enter the general tariff before
  switching resolution on.

### Phase 5 — Renters and rentals (Rules 3.3, 3.4)

- The renter's days in the yard are billed to the rental job at its job rate
  (Phase 4), instead of a zero-rated `on_hire` row.
- Rental fee billing — hire plan Phase 5 (`SHIRE`, cumulative tier pricing,
  draft invoices), on the rental job. It also bills a cargo-transfer yard box's
  days out of the yard at its agreed days-out rate (Phase 9g).
- Rental round trips: a return records the movement on the rental's job and
  ends only that round trip; the agreement closes when someone closes it (D3).
- *Deploy:* code, possibly a migration for agreement state; account mapping for
  `SHIRE`.

### Phase 6 — Job costing (Rule 5.4)

- `yard_job_id` on Storage & Handling lines, Storage-only invoice details and
  Reefer electricity lines, set from the movement's job when the line is built.
- Posting passes the job through to `gl_entries`.
- Backfill command (report by default) to tag existing lines through their
  movement / visit.
- *Deploy:* migration, backfill after reading the report.

### Phase 7 — The yard's own fleet (Rule 6)

- A master with no customer and no status (done in Phase 3f) is accepted
  everywhere: a master without a visit is *not in yard*.
- Ownership as profile: `owned` / `leased` / third-party, set deliberately;
  asset fields for owned boxes (purchase date, cost, currency, supplier,
  reference, asset number, disposal).
- Fleet entry: register owned / leased boxes; a box already standing in the
  yard enters through an opening gate-in flagged *opening balance* (D6).
- Expose the **on-hire arrival** path (box arriving from a lessor) at the gate.
- Internal use — the yard as party for storing or renting its own and on-hired
  boxes (hire plan Phase 7), on the Phase 2 rule that every such use is a job.
- *Deploy:* migrations, permission for asset fields, manual entry of the fleet.

### Phase 8 — Renumbering a container (Rule 7)

**The business agreed the approach on 23 Nov 2026, and answered D10–D15 that
day and D17–D21 on 24 Nov.**

**Approach: rename in place, keep history.** The box keeps its master record
(`container_id`); the master's number becomes the new one; a history row records
the change; nothing already recorded is rewritten. Because jobs, storage, plug
sessions and pairing hang off the id, the visit simply continues.

Rejected alternatives: a **new master** for the new number (breaks the visit —
a second stay, storage and job split, contrary to "storage continues on the same
job"); **rewriting every recorded number** to the new one (falsifies the gate-in
EIR and the CODECO already sent under the old number).

**8a — Identity by id (prerequisite, no behaviour change).** Convert the 17 files
that match by number text to `container_id`; add one resolver,
`ContainerNumbers::find($typed)`: current number first, then history (→ "now
MSCU…, renumbered on …"). Test: change a master's number directly and the box's
cycles, M&R status and invoices still read as one container. Can ship any time,
and closes the split G7.1 causes today.

**8b — Owner and custody (Rule 8, prerequisite for 8c).**

- **Owner / Principal on the master (D18):** `containers.owner_customer_id`
  already exists. It becomes the labelled *Owner / Principal* party on the
  profile, editable on the Container Master and audited.
- **Owner prefixes on the principal:** a list on the principal's customer
  record (`customer_owner_prefixes`: customer, prefix), since one line has
  several (MSC: `MSCU`, `MEDU`, …). The principal's customer code identifies
  it; the prefixes let the number propose it.
- **Gate-in field:** an optional *Owner / Principal* field, shown when the
  profile has no owner. It is pre-filled from the prefix, else from the
  Customer when the Customer is a principal-type party, and the clerk can
  change or clear it. It is saved to the profile only if the profile's owner is
  still empty (Rule 8.1a), and never overwrites one.
- **Agents:** the principal's `local_agent_id` (exists, 2 set) defines the
  "principal's side". Prompt to complete it for the active lines.
- **Backfill** `containers:owner-from-prefix`, report by default: it proposes
  `owner_customer_id` for each master from its prefix and lists unknown or
  ambiguous prefixes. `--fix` applies only the confirmed ones.
- **`ContainerCustody::of($container)`:** reads the open visit and returns the
  holder of the deepest open job, otherwise its party, otherwise the visit
  Customer. It is the job-tree version of `holdingParty()`, bounded and
  cycle-guarded.
- **Gate-in information (Rule 8.4):** when the owner is known and the chosen
  Customer is not on the principal's side, the clerk sees it. The gate-in is
  never refused for this (D16).
- *Deploy:* migration (prefix table), manual entry or confirmation of the
  prefixes and local agents, backfill after reading the report.

**8c — The renumbering flow.**

```
Visit (main job — the line / its agent, as gate-in Customer)   storage, plug, sub jobs continue
  └─ RENUMBER sub job  CCD-ER-00571/RN1   party: the requester (AR)   no gate movement
        1. Request   new number + requester's reference; old number still official
        2. Work      optional work order (marking, CSC plate) on this job
        3. Apply     effective time → history row, master number (+ owner code),
                     audit, sub job completed, charge raised on the sub job
```

- `container_number_changes`: `container_id`, `old_no`, `new_no`, `effective_at`,
  `yard_job_id`, `requested_by_customer_id`, `reference`, `reason`, `created_by`.
  No unique index on `old_no`: a number can be carried by different boxes over
  time.
- Job type `RENUMBER` (`movement_direction = commercial` — never a gate
  purpose); charge code `RENUM` (`ChargeCodeSeeder` rewrites hand edits — add by
  hand on live, see `docs/deployment-seeders.md`); permission
  `yard.container-renumber`.
- **Request (D17):** the form asks **Requested by** (the party) and **Capacity**
  (*Principal* / *Local agent*), plus the requester's reference. The yard
  decides legitimacy. If the requester is a local agent that is not the
  principal's recorded `local_agent_id`, the clerk is warned, and may proceed
  (the warning is recorded).
- **Refused, with the reason shown (D19), when:**
  - the box's Owner / Principal is not yet known;
  - the requester is not on the principal's side (Rule 8.3);
  - custody is not the principal's: the visit's Customer is another party (e.g.
    a consignee), a rental is open, or a lease-in is open (D19). The message
    names the job, e.g. "on lease-in under CCD-ER-00571/LH1 since 3 Oct;
    off-hire first";
  - the box is not in the yard on an open visit (D13);
  - the new number fails the ISO 6346 check digit;
  - the new number is another master's current number;
  - a renumbering is already pending for the box.
- **Billed (D21):** the requester selected on the request, principal or local
  agent, through its `billing_party_id` if set; the clerk can switch to the
  other party before applying.
- **Warned when:** the new number appears in some other box's history.
- **Owner prefix changes (D12):** updating the owner on the profile is offered
  and confirmed in the same apply, and recorded in the same history row.
- **Undo (D14):** a reversing history entry. It is allowed only until a
  movement, invoice line or M&R document has been recorded under the new
  number.
- **EDI (D15):** no message for the change itself. CODECO keeps each
  movement's recorded number.

**8d — Showing it everywhere.**

- Gate-out lookup accepts the former number and answers with the current one;
  the departure records the current (new) number.
- Gate pass / EIR, Container Inquiry, invoice lines and the Container Master show
  `NEW (ex OLD)` through one component; Inquiry shows the number timeline.
- Exports **append** `Former Container No`; existing columns keep their meaning.
  CODECO keeps each movement's recorded number (arrival old, departure new).
- Invoice lines keep the number they were billed under; a line raised after the
  change for days before it prints the current number with the former one.

**8e — Close the open door (G7.1).** The number becomes read-only on the master
form once the box has a movement, redirecting to the renumbering flow (the same
pattern as the equipment-type correction). A master never gated in can still
have a typo corrected.

**8f — Challenge a former number at the gate (G7.5).** A gate-in under a number
in history warns that it belongs to a box now numbered X; if X is in the yard,
the gate-in is refused as a duplicate.

*Deploy (8c–8f):* migration (history table), `YardJobTypeSeeder` (upsert,
safe), `RENUM` added by hand, `permissions:sync` + grant, no backfill (no
history exists yet). Before `RENUM`, confirm the `RN` short code is unused.

**8g — Dashboard.** Renumberings requested but not applied appear in the
open-sub-jobs component (Phase 2).

**8h — Renumbering from M&R (later, D11).** When a repair includes re-marking
the number, the M&R flow (survey → estimate → work order) can raise the
`RENUMBER` sub job, and the work order's completion applies it, through the same
service, checks and history. Planned after the M&R job linkage in Phase 3b.

### Phase 9 — Cargo transfer: one CT job, rented yard boxes, cargo out by lorry (Rule 9)

Restructures the cargo-transfer module around the rental machinery that already
exists (`ContainerHireService`, `HireGateService`), keeping its screen. No data
migration (0 transfers).

```
Main job CCD-CT-00012   Customer: ABC Traders                    (gate-in type CT)
  ├─ MSCU1234567 customer's box: in LADEN (seal) … out EMPTY (no seal) / PARTLY LOADED   [main job]
  └─ CCD-CT-00012/RL1  cargo-transfer sub job = rental of CCDU7654321 (yard-owned / leased-in)
        agreed rates · draws from the yard box's stay / lease-in (storage suspended)
        cargo-out by lorry, no charge, gate pass each: trip 1 … last trip (box empty)    [sub job]
          or CCDU7654321 out LADEN (seal) → back EMPTY                                   [sub job]
        rental closed → yard box back to stock (own stay resumed)
  main job completes when the customer's box has left and every /RLn is closed
```

- **9a — One CT job type (D26).** The system type `CARGO_RENTAL_IN` is renamed
  *Cargo Transfer*, short code **CT**; job numbers read `CCD-CT-…`. The
  hand-made `CARGO_TRNS` is deactivated. Both have 0 jobs. A CT gate-in is
  laden (normal seal rules) and shows *pending cargo transfer* until the first
  transfer.
- **9b — Cargo-transfer sub job with agreed rates (D25, D27).** Recording a
  transfer into a yard box opens a `CONTAINER_RELET` sub job **under the CT
  main job** (`…/RL1`; a second yard box gets `…/RL2`), shown as *Cargo
  Transfer*. Renter, party and holder are the main job's Customer; billing is
  AR. Its **cargo-transfer section** takes the agreed rates:
  - storage per day (free days, default 0);
  - lift on / lift off;
  - per transfer;
  - reefer electricity;
  - days out of the yard.

  They are saved as the sub job's job rates (Phase 4) and apply to **the
  rented yard box only**. They are editable until billed. The rental records
  the job it **draws the box from** (the yard's stay, or the lease-in from the
  line). That job's storage is suspended and resumed on close. A lease-in
  cannot be off-hired while its box is rented out. The lease's own AP
  agreement with the line is untouched.
- **9c — Linked containers.** `yard_job_containers` (job, container, role
  `customer_box` / `yard_box` / later `collecting`, linked / unlinked). The job
  screen, Inquiry and each master's history show every number in the job.
- **9d — Transfers as steps.** `cargo_transfers` becomes from-box → to-box
  steps: date, cargo description, quantity, *full / partial*. The
  one-per-gate-in guard goes.
- **9e — The customer's box leaves.** On the main job:
  - **empty, no seal** after a full transfer. The Phase 1 resolver treats the
    recorded transfer as the in-yard event that emptied it, so the gate form
    pre-fills Empty and asks no seal;
  - **laden** after a partial transfer: a seal, or a no-seal reason, under the
    existing gate policy (D29).

  The main job does not complete, and the gate-out warning (Phase 2) lists the
  open `/RLn`.
- **9f — Cargo out by lorry (the usual way; D30).** Each trip is a **cargo-out**
  record on the sub job:
  - **vehicle:** plate, type, transporter;
  - **driver:** name, ID / licence no., phone;
  - **cargo (optional):** description, packages / quantity and unit, weight,
    volume;
  - date / time in and out, and *last load — box now empty*.

  Each trip prints its own **cargo gate pass**, a separate format from the
  container gate pass. The yard does **not** charge for these trips; the
  customer handles the loading. A cargo-out creates no gate movement, because
  no container moves. Many trips are allowed. The last one records the yard
  box as empty and prompts closing the rental. The job screen totals what has
  left against what was transferred in, where quantities were given.
- **9g — Cargo out in the yard box.** A rental departure (`HireGateService`),
  **laden, seal required**, carrying `/RLn`. The empty return
  (`HIRE_RETURN_IN`) carries the same job and hands the box back. Days out are
  priced at the agreed days-out rate and billed through the rental billing of
  Phase 5; until Phase 5 exists, they are counted on the job and raised on a
  general invoice.
- **9h — Closing.** The rental closes deliberately (Rule 3.4) when the yard box
  is empty in the yard, or back empty. The box returns to stock with its own
  stay resumed. The main job completes when the customer's box has left and
  every `/RLn` is closed. The open-sub-jobs dashboard lists CT jobs with cargo
  still in yard boxes, showing days since the last cargo-out.
- **9i — Storage invoice, one per customer, priced per line (D27).** The
  customer's own boxes are priced by the normal tariff; rented yard boxes by
  their sub job's agreed rates. Both go on the same storage invoice, and each
  line shows its rate source (*Tariff* / *Job CCD-CT-00012/RL1*). This needs
  the Phase 4 per-row resolution (job rate → party tariff → general tariff),
  which closes G9.12.
- **9j — No master writes** (Phase 3); state on each box's movements and jobs.
- **Later phase (D28) — a collecting container.** The customer brings a box in to
  collect the cargo:
  - **Gate-in:** the clerk chooses *Collect cargo for an open CT job* and picks
    from that Customer's open CT jobs. If the Customer has none, the option is
    refused. The box joins the job (Rule 2.4a).
  - **Transfer and departure:** it receives the cargo by a transfer step,
    priced at the sub job's rates, and leaves laden with its seal.
  - **Why later:** rare in practice, so it is not built in Phase 9.
- *Deploy:* migrations (`yard_job_containers`, transfer steps, cargo-outs with
  vehicle / driver / cargo details, the rental's draw-from link), a cargo gate
  pass format (Settings, beside the existing gate pass formats), job type
  change by hand on live (rename / re-code `CARGO_RENTAL_IN`, deactivate `CARGO_TRNS`), code.

### Order and size

| Order | Phase | Size | Why here |
| --- | --- | --- | --- |
| 1 | 1 — seal / gate-out from visit, seal-change reason (D9) | small–medium | live complaint, CODECO errors |
| 2 | 8a — identity by id | small–medium | no behaviour change; closes today's split from G7.1 |
| 3 | 2 — job life cycle, gate-out sub-job warning (D5), open-sub-jobs dashboard (D3), sub-job numbering (D7/D20) | medium | every later phase needs a trustworthy job tree |
| 4 | 8b — owner and custody | small–medium | renumbering needs a known owner (only 3 of 1,930 linked today); needs the prefix list entered (D18) |
| 5 | 8c–8g — renumbering flow | medium | needs 8a, 8b and the sub-job life cycle; its charge is already job-costed (general invoice lines carry `yard_job_id`) |
| 6 | 6 — job costing | medium | cheap once jobs close; makes Rule 5 visible |
| 7 | 4 — general tariff (storage + handling) + job rates | medium | 14% of gate-ins unpriced today |
| 8 | 9 — cargo transfer: one CT job, rented yard boxes, cargo out by lorry | medium–large | needs the sub-job life cycle (Phase 2) and job rates (Phase 4); days-out billing joins Phase 5 (raised by hand until then); not used on live yet, so it can be restructured cheaply before it is |
| 9 | 3 — job state off the master (3a–3f, in slices) | large | D1 widened it (status, location, M&R status); moved ahead of Phase 5 because M&R and every in-yard screen depend on it |
| 10 | 5 — renters and rentals | large | needs Phase 4 rates |
| 11 | 8h — renumbering raised from M&R | small | needs Phase 3b (M&R on the job) |
| 12 | 7 — yard fleet | medium | needs the nullable master customer (3f) and internal-party jobs; also closes G9.8 |

If renumbering is needed on live sooner, take 8a → 8b → 8c + 8e straight after
Phase 1, and close the sub job by hand until Phase 2's life cycle exists.

---

## 5. Probe evidence

Nine probes, all passing against today's code — i.e. all nine gaps confirmed.
Each becomes a test with the opposite assertion in the phase that fixes it.
(Kept outside the repository; recreated per phase.)

| Probe | Today | Rule |
| --- | --- | --- |
| R1 — party without a tariff | main job opened for the party; stay `daily_rate 0`, `free_days 0` | 1.5 |
| R4 — gate-in laden with seal | master gets `cargo_status`, `seal_no`, `customer_id` | 4.3 |
| R5a — complete main job over open lease + rental | accepted; main `completed`, both sub jobs `open` | 5.1 |
| R5b — final gate-out | main job stays `open`, no `completed_at` | 5.2 |
| R3a — rental of a stored box | renter's storage row `on_hire`, `daily_rate 0` | 3.3 |
| R3b — rental with no renter | no sub job | 3.2 |
| R6a — master with no customer | database refuses (`customer_id` NOT NULL) | 4.4 |
| R6b — master record alone | `status = in_yard`, no movement | 4.4 |
| R6c — rent a yard-owned box never gated in | refused: "No open storage record found" | 6.4 |

---

## 6. Decisions

### Decided (23–24 Nov 2026)

| # | Question | Decision | Where it lands |
| --- | --- | --- | --- |
| D1 | Status and location: job data, or a summary on the master? | **No job information on the master at all**, not even a summary. Status, location and M&R status belong to the job; every process (M&R included) reads them from the job; the master links to the jobs, movements and number changes. | Rule 4 (4.2–4.7); Phase 3 rewritten (3a–3f) |
| D2 | Grade: per visit or profile? | Per visit | Phase 3a |
| D3 | Does a renter's return end the rental, or only the round trip? | Only the round trip; the agreement closes when someone closes it. **Plus** a dashboard component of returned-but-open and other pending sub jobs | Rule 5.6; Phase 2 |
| D4 | General tariff: storage only, or storage and handling? | Both | Phase 4 |
| D5 | Final gate-out with sub jobs open? | Never block; mark `awaiting_sub_jobs`. **Plus** a detailed warning at gate-out listing the pending sub jobs | Rule 5.5; Phase 2 |
| D6 | A yard-owned box already in the yard? | Opening gate-in flagged *opening balance* | Phase 7 |
| D7 | Sub job numbering | Must show **main vs sub** and the **purpose**, with a sequence under the main job. Format proposed in D20 | Rule 2.5; Phase 2 |
| D8 | Capture VGM per trip? | Only if a customer or line asks; on the movement | — |
| D9 | Seal changed at gate-out? | Allow, with a warning **and a mandatory reason** | Phase 1 |
| D10 | Who is billed, and who may request a renumber? | The gate-in **Customer** (owner line / NVOCC) is the billing party; the consignee is an import shipment detail. **Only the owner** may request a renumber, with the box **in the yard in the owner's custody** | Rule 1 clarified; Rule 7.4a; new Rule 8; Phase 8b |
| D11 | The physical work | A simple sub job with its own charge. **M&R to support it later** | Phase 8c; 8h |
| D12 | Owner on the profile when the prefix changes | Offered and confirmed in the same apply | Phase 8c |
| D13 | Renumber a box not in the yard? | No | Phase 8c |
| D14 | Undo a renumbering | Reversing history entry, until something is recorded under the new number | Phase 8c |
| D15 | EDI for the change | Not sent; CODECO carries each movement's recorded number | Phase 8c |
| D16 | Which customer types may be the gate-in Customer? | **All.** A consignee may bring a box for its own purposes, e.g. temporary storage on the way to another yard, or a cargo transfer into a yard box (Rule 9) | Rule 1 clarified; Rule 9; Phase 9; G1.4 closed |
| D17 | May a local agent request a renumbering? | **Yes:** the principal (often overseas) or its local agent, whichever the yard recognises as legitimate. The renumbering sub job records *Principal* / *Local agent* | Rule 7.4a; Phase 8c |
| D18 | How is the owner known? | A separate **Owner / Principal** party on the master, identified by its code. Answered in the plan: an optional field at gate-in for a box with no owner, pre-filled from the prefix or the Customer; later changes on the Container Master | Rule 8.1–8.1b; Phase 8b |
| D19 | Renumber during a lease-in? | **Refuse, and show the reason** | Rule 7.4b; Phase 8c |
| D20 | Sub-job number format | **Agreed:** `<main job no>/<purpose code><n>`, e.g. `CCD-ER-00571/LH1`. The 11 existing sub jobs keep their numbers | Rule 2.5; Phase 2 |
| D21 | Who is billed for a renumbering requested by a local agent? | **As recommended:** the requester selected on the form (principal or agent), through its billing party if set; switchable before applying | Phase 8c |
| D22 | How does a cargo transfer run? | The consignee brings the line's box **laden**, the cargo goes into a yard-owned or leased box, and the line's box goes out **empty**, **no seal required** (laden in, empty out) | Rule 9.6; Phase 9e |
| D23 | How is the transfer structured? | **CT** is identified at gate-in. The transfer module links a second container number to the same job. The yard's box is **rented** to the party, as a rental sub job under the new number. The job does not complete when the line's box leaves, because the cargo leaves later in another (the yard's) box | Rule 9; Phase 9 |
| D24 | How does the cargo leave? | **Refined 24 Nov:** usually **by lorry or other vehicles, in one or more trips** (cargo-outs, no container movement). Sometimes the customer takes the **rented yard box** out, clears it, and returns it empty. All cargo cleared → rental closed → job closed. The customer's box leaves empty or **partly loaded** | Rule 9.6–9.10; Phase 9e–9h |
| D25 | What does the consignee pay? | **Rates are defined in the cargo-transfer section of the sub job**, and apply to storage and the rest | Rule 9.3; Phase 9b |
| D26 | Which job type is CT? | **One type, CT (Cargo Transfer):** the system type re-coded; the hand-made one deactivated | Rule 9.1; Phase 9a |
| D27 | Which boxes do the transfer rates cover? | **Only the rented yard box**, for the period of the sub job, at the rates agreed when the sub job was created. The customer's own boxes (including the one it brought in) stay on the normal tariff. Both on **the same storage invoice**, each line priced from its own source | Rule 9.3–9.4; Phase 9b, 9i; Phase 4 |
| D28 | A container brought in to collect the cargo | **Later phase**, documented. The proposed flow (pick the open CT job at gate-in) is accepted. Rare in practice | Rule 2.4a, 9.12; Phase 9 (later) |
| D29 | Customer's box leaving partly loaded: seal? | **As recommended:** the normal laden rule — a seal, or a no-seal reason under the existing gate policy (`sealPolicyError`) | Rule 9.6; Phase 9e |
| D30 | Lorry trips: charged? gate pass? | **Not charged** (the customer handles them). **A separate cargo gate pass** for each trip. Record vehicle and driver details, and optionally the cargo: description, quantity, weight, volume | Rule 9.7; Phase 9f |

### Still open

None. New questions will be added here as the phases are built.

---

## 7. Related records

- `docs/architecture-principles.md` — the rules
- `docs/container-hire-rental-plan.md` — hire phases 0–7 (Phase 5 and 7 are
  Phases 5 and 7 here)
- `docs/gate-correction-plan.md` — equipment type and empty-with-seal
- `docs/deployment-seeders.md` — deploy notes per release
