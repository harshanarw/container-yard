# Deploying: migrations & seeders on a live instance

## The one thing to know first

**Laravel tracks migrations. It does not track seeders.**

There is a `migrations` table and a `migrate:status` command. There is no `seeders`
table and no `seed:status`. So "which seeders are pending?" is not a question the
framework can answer — it has to come from the change log plus the judgement below.

**`php artisan db:seed` must never be run on a live instance.** `DatabaseSeeder`
calls `UserSeeder`, `CustomerSeeder`, `ContainerSeeder`, `InquirySeeder` and
`EstimateSeeder`, which insert sample staff, customers, containers, surveys and
estimates. On live that is fabricated business data mixed into real records.
Always run individual seeders with `--class=`.

## Finding what is actually pending

Migrations are authoritative — ask the live server, not the repo:

```bash
php artisan migrate:status                     # full list, Ran / Pending
php artisan migrate:status | grep -i pending   # just the outstanding ones
```

Seeders have no equivalent. Work from the release notes / commit range, then check
each candidate against the classification below before running it.

## Seeder classification

### Safe to re-run on live — idempotent reference data

All use `updateOrCreate` / `firstOrCreate`, so re-running updates in place and adds
nothing duplicate. These carry the master data the app needs to function.

| Seeder | Contents |
| --- | --- |
| `CountrySeeder`, `CountryStateSeeder` | Countries, provinces, districts |
| `CurrencySeeder`, `BankSeeder` | Currency master, licensed banks |
| `TaxCodeSeeder`, `ChargeCodeSeeder` | Tax codes, charge codes |
| `EquipmentTypeVentilationSeeder` | Ventilation defaults on equipment types |
| `ContainerGradeSeeder`, `YardJobTypeSeeder`, `YardLocationSeeder` | Yard masters |
| `ChecklistMasterItemSeeder` | Inspection checklist items |
| `MrCodeSeeder`, `MrCodeChargeMappingSeeder` | M&R codes and charge mapping |
| `RepairCategorySeeder` | Repair categories |
| `ReeferElectricityTariffSeeder`, `WashingTariffSeeder` | Default tariffs |
| `WorkingHourSeeder`, `HolidaySeeder`, `OtTariffSeeder` | Overtime masters |
| `Finance\DefaultCoaSeeder`, `Finance\AccountMappingSeeder` | Chart of accounts, GL mappings |
| `PermissionSeeder`, `RoleSeeder`, `SystemAdminSeeder` | Access control baseline |

### Never on live — demo / sample transaction data

Idempotent or not, these fabricate business records.

`UserSeeder` · `CustomerSeeder` · `ContainerSeeder` · `InquirySeeder` ·
`EstimateSeeder` · `OptionalDemoDataSeeder` and everything it calls
(`GateMovementSeeder`, `YardStorageSeeder`, `StorageInvoiceSeeder`,
`StorageHandlingInvoiceSeeder`, `WorkOrderSeeder`, `RepairInvoiceSeeder`).

### Destructive — wipes the table before rebuilding

These delete rows the customer may have configured through the UI. Only run on a
deliberate reset, never as part of a routine deploy.

| Seeder | What it deletes |
| --- | --- |
| `RepairCategoryMappingSeeder` | **All** rows in `repair_category_mappings` |
| `DamageAssessmentRuleSeeder` | **All** rows in `damage_assessment_rules` |
| `MrTariffItemSeeder` | All tariff items under the seeded header |
| `EquipmentTypeSeeder` | Merges duplicate equipment types, repoints FKs, deletes the loser |

### Duplicates on re-run — no upsert guard

Safe the first time, doubles the rows every time after. Run once per instance.

`HandlingTariffSeeder` · `StorageTariffSeeder` · `MrTariffItemSeeder`
(also destructive, above)

### One-time backfills — run once, then never again

Rewrite existing rows to a new shape. Harmless when already applied, but they scan
the whole table; skip them once the release that introduced them is live.

`NormalizeCargoStatusSeeder` · `FixBlankCargoStatusSeeder` ·
`TariffCargoStatusSeeder` · `TariffChargeCodeBackfillSeeder` ·
`InvoiceValueBackfillSeeder` · `ContainerVentilationBackfillSeeder` ·
`DriverBackfillSeeder` · `CustomerTypeShortCodeSeeder` · `UserProfileSeeder`

## Permissions

Permissions are generated from `config/modules.php`. Whenever that file changes,
sync it — do not run `PermissionSeeder` by hand:

```bash
php artisan permissions:sync --dry-run   # preview what would be added
php artisan permissions:sync             # apply
```

It only adds missing permissions; it never revokes. New permissions still have to
be granted to roles in **Access Control**, except for super-users, who bypass the
gate entirely.

## Standard deploy sequence

```bash
php artisan down

git pull origin <branch>
composer install --no-dev --optimize-autoloader

php artisan migrate --force        # --force is required in production
php artisan permissions:sync       # only if config/modules.php changed

# Only the specific idempotent seeders the release needs, e.g.:
# php artisan db:seed --class=WorkingHourSeeder --force

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

Take a database backup before `migrate`. Blade-only releases (print layouts,
templates) need no migration or seeder at all — just `view:clear`.

## Release notes

### Overtime (OT) module

Migrations `000287`–`000291`: OT master tables, the `require_ot_receipt` company
setting, the `OTR` number sequence, `ot_receipts`, and the OT columns on
`gate_movements`.

```bash
php artisan migrate --force
php artisan permissions:sync                                    # ot.* permissions
php artisan db:seed --class=WorkingHourSeeder --force           # Mon-Fri 08:00-17:00, Sat 08:00-13:00
php artisan db:seed --class=HolidaySeeder --force               # 2026 mercantile holidays
php artisan db:seed --class=OtTariffSeeder --force              # ACDO-OT-2026-04 + 6 rate rules
php artisan db:seed --class="Database\Seeders\Finance\DefaultCoaSeeder" --force   # adds 4009 OT Revenue
php artisan optimize:clear
```

All four seeders are idempotent. `DefaultCoaSeeder` upserts the whole chart of
accounts by code — existing accounts are updated in place, none are removed, and
balances are untouched.

Afterwards, in the app: review **Settings → Overtime → OT Setup**, adjust the
working hours and holiday calendar to the site, and turn on *Require an overtime
receipt for out-of-hours gate-ins* in **Company Settings** only once the tariff is
confirmed. The setting ships **off**, so nothing is enforced until it is enabled.

### Gate pass & OT receipt print layouts

Blade only — no migration, no seeder.

```bash
php artisan view:clear
```
