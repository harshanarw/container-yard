# CLAUDE.md

Container yard management system (CYMS): gate in/out, yard jobs, storage and
handling billing, reefer plug sessions, M&R (survey → estimate → work order →
repair invoice), finance (AR/AP, GL postings), and container hire / rental.
Laravel 10, PHP 8.4 in CI (`^8.1` in composer.json), MySQL 8. The live server is
AlmaLinux + nginx + php-fpm.

Development happens on the Windows laptop (PowerShell / Git Bash) and in web
sessions, both pushing to `claude/reload-container-yard-project-0yXNt`. **Pull
before you start, push before you stop, one environment active at a time.**

## Hard rules

- **Never run `php artisan db:seed`** (bare) against a live database.
  `DatabaseSeeder` calls `UserSeeder`, `CustomerSeeder`, `ContainerSeeder`,
  `InquirySeeder` and `EstimateSeeder`, which fabricate business records.
  `HandlingTariffSeeder` and `StorageTariffSeeder` have no upsert guard, so a
  re-run duplicates every tariff row. On live, always use `--class=` with a
  seeder that `docs/deployment-seeders.md` classifies as safe.
- **Never run `db:seed --class=RolePermissionSeeder`** on live. It calls
  `sync()` on each role's permissions, which revokes every grant made by hand
  (on live at least `container-stock.view`, `weekly-revenue.view` and
  `gate-check.view`). Add permissions with `php artisan permissions:sync`
  (additive, generated from `config/modules.php`), and grant them through the
  UI under *Settings → Roles & Permissions*.
- **`ChargeCodeSeeder` is idempotent but not non-destructive.** It rewrites
  description, category, rate_type, tax_code_id and sort_order on every code it
  lists. Check for hand edits before running it on live.
- **Never `composer dump-autoload --no-dev`** on the server. It drops Sail from
  the autoload map while the package manifest still registers
  `SailServiceProvider`, and every request fatals. See
  `docs/live-deploy-hire-rental.md` §2 for the recovery steps.
- Data-repair commands (`leases:phantom-movements`, `reefer:empty-operating`,
  `containers:fix-gate-custody`, `containers:fix-storage-custody`,
  `containers:reconcile-mr-status`, `storage:reconcile`, …) **report by
  default**. `--fix` is a separate, deliberate step, taken after someone has
  read the report.
- Commit and push only when asked. Stage explicit paths, never `git add .`.

## Tests

- PHPUnit against a real MySQL testing database, `container_yard_testing`, set in
  `phpunit.xml`, with credentials from `.env.testing`. SQLite cannot be used,
  because the migrations are MySQL-specific.
- **The first test in a file takes about 70 seconds.** That is
  `RefreshDatabase` migrating the schema and running the full `DatabaseSeeder`
  (`$seed = true` in `Tests\Support\FeatureTestCase`). It is not a hang.
- **Full suite: about 5 minutes, 1,273 passing, 1 skipped.** Fewer passing
  means a regression; do not report it as "mostly green". More skips usually
  means a missing dependency on this host (several export tests
  `markTestSkipped` when openspout is absent or cannot write a styled
  workbook), so a skip count above 1 needs explaining too.
- Commands: `php artisan test`, `php artisan test --testsuite=Unit` (no DB),
  `php artisan test tests/Feature/Smoke`, `php artisan test --filter=Name`.
- Feature tests extend `Tests\Support\FeatureTestCase` and use
  `actingAsSystemAdmin()` / `actingAsRole('yard_supervisor')`. Each test is
  wrapped in a transaction that rolls back.
- Convention: each change gets a test. A new route is covered by the smoke test;
  a new workflow or rule gets a feature test asserting its side effects; a pure
  helper gets a unit test.

### Fixture-naming traps (tests/README.md has the full account)

- **Don't give a fixture the same name as a framework method.**
  `Illuminate\Foundation\Testing\TestCase` has public `session()`, `seed()`,
  `get`, `post`, `put`, `patch`, `delete`, `json`, `call`, `from`,
  `withHeaders`, `followingRedirects`, `artisan`, `travel` and `app`. A private
  method with one of those names fatals at class load and no test in the file
  runs. PHPUnit's `final` methods, `status()` among them, fail with "Cannot
  override final method". **Name a fixture for what it builds:**
  `seedBoxes()`, `makeSession()`, `mrStatus()`, `arriveEmptyReefer()`.
- **A collision with a trait method is silent and much worse.** A class's own
  method wins over a trait's with no error. `MovementPartySurfacesTest`'s
  private `workbookText()` shadowed `ReadsWorkbooks::workbookText()` and caused
  mutual recursion about 2,670 levels deep: 1 GB memory exhaustion, temp files
  piling up, 465 s for one test. **Before adding a trait to a test, grep the
  file for the trait's method names.** Inside a trait, route internals through
  names no test would pick (`readXlsxParts()`).
- When a whole file dies before any test runs and the error names a method you
  wrote, the method's name is the problem.

### Other test rules

- Call `actingAsSystemAdmin()` **before** creating anything with a NOT NULL
  `created_by` (`gate_movements`, `yard_jobs`, `reefer_plug_sessions`,
  `yard_storage`, the hire records), and pass `'created_by' => auth()->id()`
  explicitly. Otherwise you get "Field 'created_by' doesn't have a default
  value".
- Read a generated `.xlsx` only through `Tests\Support\ReadsWorkbooks`.
  openspout writes cell text inline into the sheet XML, not into
  sharedStrings, and the text is entity-encoded (`O&#039;Kon`). A hand-written
  loop misses both, and a negative assertion over raw bytes passes vacuously.
- A test must exercise the path the feature was built for. For example, a
  rental departure carries the *letting* job, not the stay's job. Assert the
  distinguishing precondition first, so the test cannot quietly degrade into the
  ordinary case (see commit 15c5489).
- Windows and macOS filesystems are case-insensitive; live and CI are not.
  Check the case of view names and class filenames.

## Domain model: what each column actually means

### Who the movement belongs to vs. who is at the gate

- **`gate_movements.customer_id` is the *visit* customer**: whose stay the
  container is on. It is deliberately kept that way (`containers:fix-gate-custody`
  forces it back if it drifts).
- **The party physically at the gate is the job's `held_by_customer_id`.** It is
  null on everything except a hire, so the two are the same party on every
  ordinary movement. **They differ on a rental**: the box leaves with the
  renter while the visit stays the shipping line's.
- The rule is stated once, in `GateMovement::holdingParty()` (job holder, falling
  back to the visit customer), with `partyLabel()` and `jobChain()` alongside
  it, and rendered by the `<x-movement-party>` component. Use these. Never read
  `customer_id` to decide who is at the gate, who pays for a lift, or who is
  named on a gate pass.
- Handling (lift on/off) is billed to `holdingParty()`, so the renter pays for
  rental release and return. The CODECO EDI deliberately stays on the line.
  Exports **append** new columns (`Party At Gate`, `Rented To`, `Rent Job`) and
  never repurpose existing ones.

### Gate movements are physical

**A job creates gate movements only when the container physically moves.** The
stay's job does so on arrival and final departure, and each rental when the box
leaves and comes back. **A lease-in never creates one**, because only
commercial custody changes. `LessorOnHireService::onHire()` fabricates a
gate-in (the legacy `arrival` mode). The screen uses `onHireInYard()`, which
creates none. `on_hire_mode` records which mode a lease used, and off-hire
dispatches on it.

### Three hire relationships (docs/container-hire-rental-plan.md)

| | Relationship | Yard is | Ledger | Code |
| --- | --- | --- | --- | --- |
| A | Lease-in from a shipping line | lessee | AP, `LHIRE` | `LessorOnHire`, job `LESSOR_ONHIRE`, menu "On-Hire In" |
| B | Re-let / rent out to a customer | lessor | AR, `SHIRE` | `ContainerHire`, job `CONTAINER_RELET`, menu "Rent Out" |
| C | Cargo substitution | neither | mixed | `CargoTransfer` |

- The names are confusing: `LessorOnHire` is the yard *paying*, and
  `ContainerHire` / `$container->activeHire` is the yard *being paid*. The
  classes were not renamed; their docblocks state the direction.
- The job tree goes three deep: stay (gate-in) → lease-in → one or more rentals,
  linked by `yard_jobs.parent_job_id`. Every walk over `parent_job_id` must be
  depth-bounded and cycle-guarded, because the column is self-referential.
- A job has `customer_id` (counterparty), `billing_direction` (`ar` | `ap`) and
  `held_by_customer_id`. On a lease-in the holder is the yard's own contact
  (`company_settings.internal_customer_id`, or a managed placeholder `SELF`).
- `movement_direction = 'commercial'` marks a job type that is never a gate
  purpose (`LESSOR_ONHIRE`, `CONTAINER_RELET`). `HIRE_RETURN_IN` is the renter
  bringing the box back. `HireGateService` / `HireGateState` decide both the
  form and the save, from one place.
- Hire and storage are mutually exclusive for a day. A lease suspends the
  line's storage and opens a zero-rated `lease_in` row. Billing reads only
  `normal` / `resumed` rows (`nonHire()`). A hire return creates no billable
  storage row.
- Hire rates are **duration tiers** (`hire_rate_tiers`, `HireTierPricing`), and
  a monthly rate is a block price that is not derived from the daily rate.
  Billing is cumulative: `owed = price(days to date) − already invoiced`.
  Nothing bills silently short; unpriced days are reported.
- Stock has three states: `In Yard`, `On Hire`, `On Hire — Rented Out`. A
  rented-out box stays on its owner's stock statement, but it is not counted
  for TEU or occupancy. Custody is selected on dates, not on live `status`.
- Visit pairing (arrival ↔ departure) lives once, in `App\Support\VisitPairing`:
  job link inside the visit, then the clock inside the visit, then the job link
  regardless of dates. Don't write another copy.
- Phase 5 (sub-hire AR billing) and Phase 7 (internal hire) are not built yet.

### Storage, sizes and corrections

- `yard_storage` carries no size or equipment type. Storage resolves the type
  **from the container master at preview time**, so correcting the master is
  what re-prices an unbilled stay.
- Only storage, handling and washing are size-sensitive. Crossing the reefer
  boundary (`RF`/`RH`, `EquipmentType::isReefer()`) changes what the box *is*.
- The equipment-type correction (permission `yard.equipment-type-change`)
  writes the master and every movement of the visit, walking the whole job tree.
  It refuses if the cycle is billed, or if it crosses the reefer boundary while
  plug sessions exist. `updateMovement` no longer accepts `equipment_type_id`.
  The Container Master screen redirects to the gate-in for a container that has
  movements.
- A seal number on an **Empty** container is refused (`block_seal_on_empty_in` /
  `_out`, migration 000321). `cargo_status` selects the tariff row, so a laden
  box recorded as empty under-bills. The rule fires on the submitted value.
- Reefer storage tariffs need a row for each `reefer_mode` (operating /
  non_operating), or a null mode. An empty reefer defaults to NOR.
- `CompanySetting::current()` is cached for an hour. Run `php artisan cache:clear`
  after a migration that adds settings columns.

## Repository state

- The Laravel skeleton (`composer.json`, `artisan`, `bootstrap/`, kernels,
  `.env.example`) was committed in c122085. Before that the repo could not be
  cloned and run, and `.github/workflows/tests.yml` had failed on every push
  because there was no `composer.json`. `docs/local-development-setup.md` and
  §2 of `docs/live-deploy-hire-rental.md` still describe the pre-c122085 state
  ("this repository does not contain composer.json"). Don't assume CI is
  green; check it.
- `SEEDER_GUIDE.md` says "seeders are idempotent". That is wrong for
  `HandlingTariffSeeder`, `StorageTariffSeeder`, `MrTariffItemSeeder` and the
  destructive ones; `docs/deployment-seeders.md` is the authority.
- This file is committed so every session shares it. Per-machine settings
  (`.claude/settings.local.json`, permission allowlists) stay under `.claude/`,
  which `.gitignore` excludes. Keep `git commit`, `git push` and anything
  touching `.env` out of those allowlists.
- Migrations are numbered `2024_01_01_000NNN_*`; the latest is 000321.
  Laravel tracks migrations but not seeders, so which seeders a release needs
  comes from the release notes in `docs/deployment-seeders.md`.
- `docs/*-plan.md` files are design records: findings, decisions, phases.
  Commit messages carry the reasoning behind changes. Read both before
  reworking an area.

## Deploying (docs/live-deploy-hire-rental.md, docs/deployment-seeders.md)

On live, in order: back up the database (`mysqldump --single-transaction`),
`migrate:status | grep -i pending`, `migrate --force`, `cache:clear`,
`permissions:sync --dry-run` then `permissions:sync`, then only the specific
`--class=` seeders the release names, then `config:clear`, `route:clear` and
`view:clear`, then `chown -R nginx:nginx storage bootstrap/cache` and
`systemctl restart php-fpm`. Data-repair commands come after that, dry run
first, in the order the runbook gives (e.g. tariff NOR rows before
`reefer:empty-operating --fix`). Every deploy note added to
`docs/deployment-seeders.md` should say whether it needs a migration, a seeder,
a permission and a manual UI step.

## Further reading

- `docs/deployment-seeders.md`: which seeders are safe, never-on-live,
  destructive, duplicating or one-time, with release notes per change
- `docs/live-deploy-hire-rental.md`: ordered live runbook for migrations
  000310–000320
- `docs/container-hire-rental-plan.md`: the hire design, phases 0–7
- `docs/gate-correction-plan.md`: equipment-type correction and the
  empty-with-seal rule
- `docs/local-development-setup.md`: Windows and macOS setup
- `tests/README.md`: test layers, the fixture traps in full, the coverage map
- `SEEDER_GUIDE.md`: what the core and optional demo seeders create (local
  only)
