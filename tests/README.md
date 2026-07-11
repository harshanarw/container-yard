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

## Coverage roadmap (planned next phases)

Phase 1 (this batch): harness + factories + CI, smoke across all screens, and
E2E for user↔role linkage. Still to add:

- Gate-in → movement + yard job + gate pass + share code
- Gate-out → storage closed + gate pass
- Survey → Estimate → approve → Work Order → Repair Invoice
- Storage & Handling / Reefer billing generation
- General Invoice: create → issue (GL posting) → receipt/settlement → AR aging
- Container hire on/off
