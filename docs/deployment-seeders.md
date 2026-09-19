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

### Container hire / rental — the gate (2d-ii)

No new migration. The change is code plus one seeder, and the seeder is the part
that must not be skipped: the rental gate flow selects job types by code, and
two existing types change direction.

```bash
php artisan db:seed --class=YardJobTypeSeeder --force
php artisan view:clear && php artisan route:clear && php artisan config:clear
chown -R nginx:nginx storage bootstrap/cache
systemctl restart php-fpm
```

`YardJobTypeSeeder` upserts by `job_type_code`, so it is idempotent and removes
nothing. It makes three changes:

| Code | Change |
| --- | --- |
| `HIRE_RETURN_IN` | **new** gate-in type — the renting customer brings a let container back |
| `LESSOR_ONHIRE` | `gate_in` → `commercial`; it leaves the gate-in dropdown |
| `CONTAINER_RELET` | `gate_in` → `commercial`; likewise |

`commercial` is a third value for `movement_direction`, meaning *never a gate
purpose*. Both of those job types are agreements rather than movements — they
are opened by their own screens, which also handle the storage split and the
parent job — and offering them at a gate invited an operator to record an
arrival that never happened. Existing `yard_jobs` rows pointing at either type
are unaffected; only which dropdowns the type appears in changes.

**Never run a bare `php artisan db:seed`** on this database: `HandlingTariffSeeder`
has no guard and would duplicate every tariff.

### The yard's own contact (migration 000318)

A lease-in is the one job where the counterparty and the holder differ: the
shipping line is still who the agreement is with, but for the length of the
lease the yard holds the box. Until now the yard was represented by a contact
the system created for itself, with the reserved code `SELF`.

```bash
php artisan migrate --force        # 000318 — company_settings.internal_customer_id
php artisan view:clear && php artisan route:clear && php artisan config:clear
```

**Nothing is required afterwards.** Left unset, the behaviour is exactly what it
was: the placeholder is created the first time a container is taken on hire.

**On an installation that already has one**, go to *Settings → Company →
This Yard's Contact* and pick the contact you already use for the company.
Saving moves the existing on-hire history to it and reports how many jobs
moved; the previous record loses its *Internal* tag and becomes an ordinary
contact again. Only `LESSOR_ONHIRE` jobs are repointed, so a contact that also
rents containers keeps its own rentals.

Two related guards ship with it:

- the managed placeholder no longer appears in customer dropdowns (a contact
  you map yourself still does — you chose a party you already trade with);
- the contact representing the yard can no longer be deleted. It holds no
  containers, so the old guard let it through, and `held_by_customer_id` is
  `nullOnDelete` while `holder()` falls back to the counterparty — deleting it
  turned every lease-in from *held by the yard* into *held by the shipping
  line*, across the whole history, with nothing said at the time.

### Container hire charges — the payable side (migration 000319)

The AP half of the rental trade. A lease has carried its own job since it was
written and `JobPnlService` accrues the per-diem as WIP cost, but nothing ever
turned that into a bill — a completed lease produced no payable at all.

```bash
php artisan migrate --force                              # 000319
php artisan db:seed --class=ChargeCodeSeeder --force     # adds the `hire` category
php artisan view:clear && php artisan route:clear && php artisan config:clear
```

`ChargeCodeSeeder` upserts by code and is idempotent. It adds two codes, kept
apart on purpose — netted into one, the margin on a lease could not be read at
all, because the cost and the revenue would land in the same bucket:

| Code | Direction | Meaning |
| --- | --- | --- |
| `LHIRE` | payable | what the yard pays a line for a box it holds on hire |
| `SHIRE` | receivable | what the yard is paid for letting that box out (phase 5) |

**One manual step, and the screen refuses to raise anything without it:** map an
expense account to `LHIRE` under *Finance → Account Mappings* (`charge_expense`).
A cost with nowhere to post is refused rather than raised half-formed.

Then *Finance → Payables → Container Hire Charges*: pick a period, review what
is owed on each open lease, and raise one **draft** supplier invoice per
shipping line. Draft, never posted — agreeing a debt is a person's decision, and
the existing approve-and-post flow applies unchanged from there.
