# Dusk Browser Regression Suite — Test Plan

Browser (end-to-end) tests that exercise the app **in a real Chrome browser**, so
they cover JavaScript behaviour and full multi-step flows the PHPUnit feature
tests can't reach. **Local / CI only — never run against production.**

## Why Dusk (and what stays in feature tests)

| Layer | Tool | Covers |
|---|---|---|
| Business logic, validation, DB state, permissions | PHPUnit feature tests (`tests/Feature`) | the bulk — fast, keep here |
| JS interactions + critical end-to-end happy paths | **Dusk** (`tests/Browser`) | reveals, typeahead, tabs, modals, OCR buttons, one clean walkthrough per module |

Dusk is slower and more brittle, so keep it to the **critical path + the JS layer**.
Don't re-test business rules here that a feature test already proves.

## One-time setup (local)

```bash
composer require --dev laravel/dusk
php artisan dusk:install          # generates tests/Browser + Tests\DuskTestCase
php artisan dusk:chrome-driver    # match your local Chrome version
```

Create `.env.dusk.local` pointing at a **separate** database and your local URL:

```
APP_URL=http://127.0.0.1:8000
DB_DATABASE=container_yard_dusk   # a throwaway DB — Dusk migrates/refreshes it
```

Run:

```bash
php artisan serve                 # or use Dusk's built-in server
php artisan dusk                  # runs everything in tests/Browser
php artisan dusk --filter=SealReasonRevealTest   # a single test
```

## Base class & database reset

Browser tests extend **`Tests\Browser\BrowserTestCase`**, which:
- resets the DB with **`migrate:fresh --seed`** (up-only) — *not* the
  `DatabaseMigrations` trait, because this app has irreversible `down()`
  migrations (a rollback fails with "cannot drop index needed in a foreign key
  constraint"). Fresh + seed also gives pages their master data.
- **seeds once per run**, not per test (the full seeder is the main start-up
  cost). Tests in a run share one seeded database, so keep them independent
  (unique container numbers, per-test users). Set `DUSK_FRESH_PER_TEST=1` in
  `.env.dusk.local` for full per-test isolation (slower).
- lets you **watch the browser**: add `DUSK_HEADLESS_DISABLED=true` to
  `.env.dusk.local` to open a visible Chrome window (default is headless).
  Remove that line to go back to headless. Also disables Chrome background
  networking to cut down the GCM registration-retry noise.

## Conventions

- One test class per flow, under `tests/Browser`, extending `Tests\DuskTestCase`.
- Log in with `->loginAs($user)`; build state with factories in the test (RefreshDatabase-style trait `DatabaseMigrations` on the Dusk case).
- Enable company settings via `DB::table('company_settings')->update([...])` +
  `CompanySetting::flushCache()` (same cache caveat as the feature tests).
- Prefer stable selectors: element **ids** (`#noSealWrapIn`, `#cargoStatusIn`) and
  `name="..."`. Use `waitFor` / `waitUntilMissing` around anything JS toggles.
- Assert visible **and** invisible states (reveal tests must prove both directions).

---

## Test-case matrix

Legend: **[JS]** = only Dusk can verify · **[E2E]** = full happy-path walkthrough ·
✅ = already covered by a feature test (list here only if a browser check adds value).

### Yard — Gate In
- **[E2E]** Empty gate-in happy path: fill required fields → confirm modal → save → lands on gate pass; container in yard.
- **[JS]** No-seal reason reveals when cargo = Laden, hides for Empty (policy on).
- **[JS]** No-seal reason reveals when a **laden Job Type** is selected (cargo auto-set fires the reveal).
- **[JS]** Blank seal on a laden save → red error notification shown, stays on form (422 path).
- **[JS]** Vehicle Number blank → error shown on save (required).
- **[JS]** Duplicate container (already in yard) → error notification, no silent no-op.
- **[JS]** Container-number field masking (4 letters + 7 digits) and check-digit hint.
- **[JS]** Driver typeahead: type NIC/name/phone → dropdown → pick fills all three fields.
- **[JS]** Guard Post status banner appears on type/scan; "Use this capture" prefills the form.
- **[JS]** Reefer service-type field appears for a laden reefer equipment type.

### Yard — Gate Out
- **[E2E]** Gate-out happy path: pick in-yard container → lookup panel populates → save → gate pass.
- **[JS]** Container Select2 lookup renders the info panel (equipment, customer, days).
- **[JS]** Under-repair / not-releasable container → block message shown at selection.
- **[JS]** No-seal reason reveals when the looked-up container is laden.
- **[JS]** Hold / active-hire / no-PTI rejection → error notification on save (422 path).
- **[JS]** Tab toggle: switching Gate In / Gate Out shows the right card + "Recording:" bar.

### Guard Post
- **[E2E]** Capture create: fill container/vehicle/driver, submit → status page shows Pending.
- **[JS]** Photo capture/upload previews render; OCR "scan" button populates the number.
- **[JS]** Driver typeahead on the capture form fills name/NIC/phone.
- **[E2E]** Review Queue: ops clears a capture → status becomes Cleared.
- **[E2E]** Promote a cleared capture to Gate-In → gate form pre-filled + rich verification panel with photos + lightbox.

### Drivers (master)
- **[E2E]** Drivers list loads; search by name/NIC/phone narrows rows.
- **[E2E]** Driver detail: edit name/phone → saved; movement-history timeline renders.
- **[JS]** Merge a duplicate from the "possible duplicates" panel → confirm dialog → survivor remains, history repointed.
- **[JS]** Non-management role → 403 (or nav link hidden).

### Repair (Survey → Estimate → Work Order → Invoice)
- **[E2E]** Create a survey with damage rows (add/remove rows, washing capture) → save.
- **[JS]** Damage-rule search / M&R code autocomplete populates line fields.
- **[E2E]** Generate an estimate from the survey → approve (portal link) → work order → repair invoice issued.

### Billing
- **[E2E]** Storage invoice: calculate → create → issue → posted to ledger (confirm status badges).
- **[E2E]** Storage & Handling invoice: line grid add/edit (JS totals) → issue.
- **[E2E]** General invoice: header job picker + line dimensions → issue → IRD serial shown.
- **[JS]** Reefer billing: completed session → bill → issue.

### Finance
- **[E2E]** Receipt settlement: full receipt marks invoice Paid; partial → Partially Paid.
- **[E2E]** AR aging report renders buckets; job-margin / P&L report screens load.

### Masters & Settings
- **[JS]** A master CRUD (e.g. Equipment Types / Container Grades): add via modal, edit inline, toggle active, drag-reorder.
- **[JS]** Company Settings: toggle a policy (e.g. require-seal-for-laden) → save → persists (checkbox stays on).
- **[JS]** Tariff screens: add a rate line (cargo status / size) → appears in the grid.

---

## Example: the seal-reason reveal (ready to use after `dusk:install`)

See `SealReasonRevealTest.php` in this directory. It logs in, enables the policy,
visits the gate page, and asserts the No-seal reason field hides/reveals as the
cargo status changes — the exact JS behaviour PHPUnit cannot check.

## Suggested build order

1. `SealReasonRevealTest` (provided) — proves the toolchain works end to end.
2. Yard Gate-In / Gate-Out E2E + JS reveals (highest-value, most JS).
3. Guard Post capture → clear → promote.
4. Drivers, then Repair, Billing, Finance, Masters — one E2E each first, JS extras after.

Implement incrementally and run `php artisan dusk --filter=<Class>` as you go —
browser tests are best grown one flow at a time, not generated all at once.
