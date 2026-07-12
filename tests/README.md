# Regression test suite

Automated tests that guard the critical end-to-end workflows before major
deploys. Runs on **PHPUnit** against a real **MySQL** test database (the schema
uses MySQL-specific migrations, so SQLite can't be used).

## Layers

| Layer | Location | Purpose |
|-------|----------|---------|
| **Smoke** | `tests/Feature/Smoke` | Every parameter-less GET page renders without a 5xx error (auto-covers new routes). |
| **Feature / E2E** | `tests/Feature/**` | Critical workflows through HTTP + asserted DB side effects. |
| **Unit** | `tests/Unit/**` | Pure logic (currency, terms, posting, resolvers) — no DB. |

## One-time local setup

1. Create the test database:
   ```sql
   CREATE DATABASE container_yard_testing;
   ```
2. Copy the env template and set your DB credentials + an app key:
   ```bash
   cp .env.testing.example .env.testing
   php artisan key:generate --env=testing
   ```
   (`phpunit.xml` already forces `DB_CONNECTION=mysql` and
   `DB_DATABASE=container_yard_testing`; `.env.testing` supplies host/user/pass.)

## Running

```bash
php artisan test                     # whole suite
php artisan test --testsuite=Unit    # fast, no DB
php artisan test tests/Feature/Smoke # smoke only
php artisan test --filter=UserRole   # a single test
```

The feature suite migrates the schema fresh and runs the full `DatabaseSeeder`
once per run (baseline master data, roles, permissions, sample records), then
wraps each test in a transaction that rolls back — so tests stay isolated.
Base class: `Tests\Support\FeatureTestCase`.

> If the full seeder makes the suite slow, swap `protected bool $seed = true;`
> in `FeatureTestCase` for a lean seeder that only loads the master/reference
> data your tests need.

## CI

`.github/workflows/tests.yml` spins up MySQL 8, installs dependencies, and runs
the suite on every push / PR. **A red build blocks the change** — this is what
keeps the suite honest per commit.

## The convention: a test per change

Automated tests don't write themselves. The rule of thumb:

- **New screen / route** → the smoke test covers it automatically. If it needs
  setup to render, add its URI to `$skipUris` (or give it a feature test).
- **New or changed workflow / business rule** → add or update a feature test
  asserting the new behaviour and its side effects (status, GL entries, balances).
- **New pure helper / calculation** → add a unit test.

Keep the suite green: never merge with a failing test — either fix the code or
update the test to match the intended new behaviour.

## Writing a feature test

```php
use Tests\Support\FeatureTestCase;

class MyFlowTest extends FeatureTestCase
{
    public function test_something(): void
    {
        $this->actingAsSystemAdmin();          // or actingAsRole('yard_supervisor')
        $response = $this->post(route('...'), [...]);
        $response->assertRedirect(route('...'));
        $this->assertDatabaseHas('...', [...]);
    }
}
```

## Coverage map

**Phase 1** — harness + factories + CI, smoke across all screens, and E2E for
user↔role linkage.

**Phase 2** — critical money + movement workflows (all green):

| Flow | Test | Asserts |
|------|------|---------|
| Gate-out releasability | `Yard/GateOutReleasabilityTest` | in-repair blocked at lookup + on POST; in-yard releasable |
| Gate-in | `Yard/GateInFlowTest` | container in-yard + movement + yard job + share code; minimal-payload variant |
| General invoice → GL | `Billing/GeneralInvoiceFlowTest` | draft → issue → posted `invoice_postings`; minimal-payload variant |
| Repair chain → GL | `Billing/RepairInvoiceFlowTest` | approved estimate → repair invoice → issue → posted |
| Reefer billing → GL | `Billing/ReeferBillingFlowTest` | completed session → invoice → session billed → issue → posted |

Bugs these tests surfaced and fixed: repair-invoice route-model binding
(issue/cancel/payment silently failed), and undefined-key fatals on partial
payloads in the gate-in / general-invoice / repair-invoice stores.

### Still to add

- Survey → Estimate → approve → Work Order (front half of the repair chain)
- Storage & Handling billing generation (multi-line storage + handling)
- General Invoice: receipt / settlement → AR aging
- Container hire on / off

### Known open finding (not yet actioned)

All invoice observers (general / repair / reefer / storage) **catch and log**
GL-posting failures, so an invoice can reach `issued` with no ledger entry when
posting can't resolve (e.g. no open accounting period, unmapped account). This
is a deliberate "non-blocking post" design — whether issuing should instead
block on posting failure is a finance product decision, still to be made.
