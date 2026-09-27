# Live deployment — the hire / rental work (migrations 000310–000320)

The commands to run on the live server, in order, for the set of changes pulled
in this batch. AlmaLinux + nginx + php-fpm, run as `root`.

Everything below is either idempotent or a dry run, with two exceptions that are
called out where they appear: `migrate --force`, and `ChargeCodeSeeder`.

**No `artisan down` / `artisan up`.** Nothing here rewrites existing rows on the
way in — the migrations only add columns and indexes, and all three seeders
upsert. The two statements that take a table lock are named in step 3; run that
step at a quiet moment instead of taking the site down.

Replace `/var/www/container-yard` with the real document root and `APPDB` with
the database name if the `.env` lookup does not suit.

---

## 0. Pull again first

Two commits landed after this batch was pulled, and one of them matters for
step 5:

- the charge-code category fix — `LHIRE` and `SHIRE` are seeded under a `hire`
  category that the master screen could not show, filter by, or save back;
- the corrected deployment note for the Drivers permission (see step 4).

```bash
cd /var/www/container-yard
git pull origin claude/reload-container-yard-project-0yXNt
git log --oneline -3
```

The top commit should read *Register the `hire` charge-code category on the
model*.

## 1. Back up the database

Not optional. Eleven migrations, one of which rewrites a column type on
`yard_storage`.

```bash
cd /var/www/container-yard
DB=$(awk -F= '/^DB_DATABASE=/{print $2}' .env | tr -d '"'"'"' ')
mysqldump --single-transaction --routines --triggers -u root -p "$DB" \
  > /root/pre-hire-deploy-$(date +%F-%H%M).sql
ls -lh /root/pre-hire-deploy-*.sql
```

Check the dump is not empty and ends with a `-- Dump completed` line:

```bash
tail -2 /root/pre-hire-deploy-*.sql
```

## 2. The autoloader — usually nothing to do

This batch adds ~20 new classes (`app/Support/VisitPairing.php`,
`app/Support/HireGateState.php`, `app/Services/HireGateService.php`, the billing
services, three console commands), all under `App\`, which is a PSR-4 prefix.

**No `composer install`, and normally no dump either.** This repository does not
contain `composer.json`, `composer.lock`, `vendor/` or `bootstrap/` — they exist
only on the server — so no pull can change the dependency set. And
`composer dump-autoload -o` builds a classmap while *keeping* the PSR-4 fallback,
so a new file under `app/` is found at runtime whether or not the classmap knows
about it.

The one setup that needs a dump is an **authoritative** classmap, where the
fallback is switched off and a class absent from the map does not exist:

```bash
grep -riE 'classmap-authoritative|apcu-autoloader' composer.json deploy* 2>/dev/null
```

If that matches, and only then:

```bash
cd /var/www/container-yard
composer dump-autoload -o
chown -R nginx:nginx vendor/composer
```

> ### Never `--no-dev` on this server
>
> An earlier version of this runbook said `composer dump-autoload -o --no-dev`.
> It takes the site down:
>
> ```
> In ProviderRepository.php line 206:
>   Class "Laravel\Sail\SailServiceProvider" not found
> ```
>
> `--no-dev` regenerates the map from the non-dev dependency set, so
> `Laravel\Sail\` is dropped from `autoload_psr4.php`. Sail is still in
> `vendor/` and still listed in `vendor/composer/installed.json`, so Laravel's
> package manifest goes on registering `SailServiceProvider` — a provider the
> autoloader can no longer resolve. Every request and every artisan command then
> fatals. The autoload files are written *before* the failing
> `package:discover`, so the error message is the damage already applied, not a
> refusal.
>
> Recover by dumping again without the flag:
>
> ```bash
> rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
> composer dump-autoload -o
> php artisan --version
> chown -R nginx:nginx vendor/composer bootstrap/cache
> systemctl restart php-fpm
> ```
>
> A vendor tree genuinely without dev packages is a different operation —
> `composer install --no-dev --optimize-autoloader`, which removes them from
> `vendor/` and from `installed.json` together, so the manifest stops naming
> them. That is a deliberate change to what is deployed, not a step in the
> middle of a migration run.

## 3. Run the migrations

Confirm what is pending first — it should list exactly eleven, 000310 to 000320:

```bash
php artisan migrate:status | grep -i pending
```

```bash
php artisan migrate --force
```

Two of these take a brief metadata lock on a table that grows:

| Migration | Statement | Note |
| --- | --- | --- |
| 000310 | index on `gate_movements (vehicle_plate)` | online in MySQL 5.7+/8, still an index build |
| 000316 | `ALTER TABLE yard_storage MODIFY COLUMN hire_type` | enum widened; a table rebuild on older MySQL |

Both are seconds on a yard-sized table. Run the step outside gate hours if
`gate_movements` is large.

Nothing is backfilled. Every added column has a default that keeps existing rows
meaning what they meant — `on_hire_mode` defaults to `arrival`,
`enforce_reefer_plug_session` to `false`, `hire_pricing_mode` to
`daily_fallback`, `hire_month_days` to 30.

## 4. Clear the settings cache

`CompanySetting::current()` memoises the whole settings **model** under one cache
key for an hour, so the cached instance is one serialised before step 3, without
the new columns. Nothing breaks on it — every reader defends the absence
(`hire_month_days ?? 30`, and a null flag is a falsy flag) — but the settings
chosen in part 9 would be ignored for up to an hour, which looks exactly like
the setting not working.

```bash
php artisan cache:clear
```

## 5. Sync the permission catalogue

Additive — `permissions:sync` inserts what `config/modules.php` declares and
grants nothing. This batch adds three: `masters.drivers.view`, `.edit`,
`.delete`.

```bash
php artisan permissions:sync --dry-run
php artisan permissions:sync
```

> **Do not run `db:seed --class=RolePermissionSeeder` on this server.** It
> `sync()`s each role's permission set to exactly what it declares, which
> revokes every grant made by hand since the install — on this instance at
> least `container-stock.view`, `weekly-revenue.view` and `gate-check.view`.
> `administrator` survives on its `*`; every other role loses them silently, and
> the first anyone knows is a 403 on a report that worked yesterday. The grant
> is a UI step, in part 9.

## 6. The three seeders

Always `--class=`. **Never a bare `php artisan db:seed`** on this database:
`DatabaseSeeder` runs `UserSeeder`, `CustomerSeeder`, `ContainerSeeder`,
`InquirySeeder` and `EstimateSeeder`, which fabricate business records, and
`HandlingTariffSeeder` has no guard and would duplicate every tariff.

### 6a. Job types — the one that must not be skipped

The rental gate flow selects job types by code, and two existing types change
direction.

```bash
php artisan db:seed --class=YardJobTypeSeeder --force
```

| Code | Change |
| --- | --- |
| `HIRE_RETURN_IN` | **new** gate-in type — the renting customer brings a let container back |
| `LESSOR_ONHIRE` | `gate_in` → `commercial`; leaves the gate-in dropdown |
| `CONTAINER_RELET` | `gate_in` → `commercial`; likewise |

Upserts by `job_type_code`. Existing `yard_jobs` rows pointing at either changed
type are unaffected — only which dropdowns the type appears in changes.

### 6b. Customer types

Adds one row, `Internal`, which tags the party record representing the yard
itself. Upserts by name.

```bash
php artisan db:seed --class=CustomerTypeSeeder --force
```

### 6c. Charge codes — read this before running it

Adds `LHIRE` (payable to a lessor) and `SHIRE` (receivable from a renter), both
`per_day`, under the `hire` category.

It is idempotent, but **not non-destructive**: the upsert rewrites
`description`, `category`, `rate_type`, `tax_code_id` and `sort_order` on *every*
code it lists, so anything an operator tuned on *Masters → Charge Codes* goes
back to the seeder's value. Check what would change first:

```bash
php artisan tinker --execute="\App\Models\ChargeCode::orderBy('code')->get(['code','description','tax_code_id'])->each(fn(\$c)=>print(\$c->code.' | '.\$c->description.' | tax '.\$c->tax_code_id.PHP_EOL));"
```

If those descriptions and tax codes are the seeder's, run it:

```bash
php artisan db:seed --class=ChargeCodeSeeder --force
```

If any were edited on the live instance, **skip the seeder** and add the two
codes by hand in *Masters → Charge Codes* — category **Hire & Rental**, rate type
**Per Day**, tax code the same VAT+SSCL code the other service charges use.

Either way, confirm both exist:

```bash
php artisan tinker --execute="\App\Models\ChargeCode::whereIn('code',['LHIRE','SHIRE'])->get(['code','category','rate_type'])->each(fn(\$c)=>print(\$c->code.' '.\$c->category.' '.\$c->rate_type.PHP_EOL));"
```

## 7. Clear the compiled caches

`routes/web.php`, `config/modules.php` and ~40 Blade views changed.

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Only if this server runs with cached config and routes (check for
`bootstrap/cache/config.php` and `routes-v7.php`), rebuild them:

```bash
ls bootstrap/cache/
php artisan config:cache
php artisan route:cache
```

Do not run `php artisan optimize` if the app relies on `.env` at runtime after
config caching — the clears above are sufficient on their own.

## 8. Ownership and restart

```bash
chown -R nginx:nginx storage bootstrap/cache
systemctl restart php-fpm
systemctl status php-fpm --no-pager | head -5
```

Then load one page and watch the log for a minute:

```bash
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

---

## 9. Four steps in the UI, and the order matters

Nothing below can be done from the shell, and the first one blocks the hire
billing screen outright.

### 9a. Map an expense account to `LHIRE` — *Finance → Account Mappings*

Mapping type `charge_expense`, source the `LHIRE` charge code, an active account.
Until this exists, *Finance → Payables → Container Hire Charges* refuses to raise
anything and says so: a cost with nowhere to post is refused rather than raised
half-formed.

`SHIRE` needs no mapping yet — the receivable side is phase 5 and is not built.

### 9b. Grant **Drivers** — *Settings → Roles & Permissions*

`DriverController` used to check `users.role`, the label column; it now checks
`masters.drivers.view` / `.edit` / `.delete` like every other master. Until the
grant, the Drivers screen 403s for everyone except a system admin.

Grant all three to **`yard_supervisor`** and **`administrator`** — the two the
old role check allowed.

### 9c. Pick the yard's own contact — *Settings → Company Settings*

The contact representing this company, used wherever the yard is itself a party
to a job — which is what holding a container on hire is.

A yard that has been running for years usually already has such a record, used
for internal storage or inter-company billing. Choose it. Leave it unset and the
app creates a managed placeholder with the reserved code `SELF` on first use,
which works but splits the company's history across two ledgers.

Doing it **before** the first lease-in is cleanest, but it is safe afterwards:
changing the setting moves the existing on-hire jobs to the newly chosen record
and reports how many moved.

### 9d. Rate tiers on each hire — *On-Hire In* and *Rent Out*

A lease or a letting with no rate tiers is not billable, and the billing screen
will say the period has nothing to bill rather than guess a rate. Set the tiers
when the agreement is recorded.

Leave *Require a reefer plug session* (Company Settings) **off** for now. It
ships off deliberately; turn it on once the gate staff are recording sessions.

---

## 10. Correcting the records this batch changed the meaning of

Each of these is a **dry run by default**. Run it, read the report, and only then
re-run with the flag. In this order — each one's output feeds the next.

### 10a. Phantom lease movements

`LessorOnHireService::onHire()` fabricated a gate-in when a container already on
the ground was taken on hire, and a gate-out at off-hire. The box never moved, so
the gate search shows a departure that never happened and the stock reports drop
the container from the line's list.

```bash
php artisan leases:phantom-movements
php artisan leases:phantom-movements --fix
```

`--fix` deletes the fabricated arrivals and converts those leases to `in_yard`.
Separately, and only where the lease period has **not** been invoiced yet
(it refuses otherwise, by design):

```bash
php artisan leases:phantom-movements --fix-storage
```

That suspends the shipping line's storage for the lease period — the yard cannot
bill a line for storing a container it is simultaneously paying that same line
rent for.

### 10b. Empty reefers reading "PTI due"

```bash
php artisan reefer:empty-operating
php artisan reefer:empty-operating --fix
```

Records them as non-operating and refreshes the M&R status.

### 10c. Stale condition and stranded repair status

```bash
php artisan containers:fix-condition
php artisan containers:fix-condition --fix

php artisan containers:fix-repair-status
php artisan containers:fix-repair-status --fix
```

### 10d. Gate custody — compare against the earlier dry run

```bash
php artisan containers:fix-gate-custody
```

**Expect more rows than the last time this was run.** The visit-pairing rule
changed: a departure carrying a job link that no longer matched its arrival used
to be discarded, and now pairs by time. The extra rows are departures that were
previously invisible to this command, not new damage.

Read the report, confirm the container numbers look like the rental round trips,
then:

```bash
php artisan containers:fix-gate-custody --fix
```

### 10e. M&R status — last, so it recomputes from the corrected data

```bash
php artisan containers:reconcile-mr-status
php artisan containers:reconcile-mr-status --fix
```

### 10f. Storage rows

```bash
php artisan storage:reconcile
php artisan storage:reconcile --fix
```

### 10g. Reefer sessions never plugged in (report only)

```bash
php artisan reefer:unplugged-sessions
```

`--mark-not-plugged` takes `--id=` and is per-session on purpose — whether a box
was plugged in is a fact somebody has to know, not one to infer in bulk.

---

## 11. What to check before calling it done

1. **Gate In/Out** — the gate-in purpose list no longer offers *Lessor On-Hire*
   or *Container Re-let*, and does offer *Hire Return In*.
2. Type a rented container's number into the gate form: it should resolve to
   *On Hire · Rented Out*, pre-select *Hire Return In*, and show the renting
   party, not the original line.
3. **Operations → Yard** menu: *Gate In/Out*, *Yard Overview*, *On-Hire In*,
   *Rent Out*, *Cargo Transfers*, *Reefer Plug Sessions*, *Yard Jobs*. Storage
   Calculator now sits under **Billing**, Drivers under **Setup → Gate
   Operations**.
4. **Container Inquiry** on a leased box: one arrival, not two.
5. **Billing → Storage & Handling**: no storage for the lease period, the
   lift-on for a rental gate-out billed to the renter, and no repeated lift-off
   against the line.
6. **Finance → Payables → Container Hire Charges**: pick a period and confirm a
   preview appears for each open lease.
7. `storage/logs/laravel-*.log` clean for the first hour of gate traffic.

## Rolling back

Every migration in this batch has a working `down()`, and they reverse in
sequence:

```bash
php artisan migrate:rollback --step=11 --force
```

That drops the added columns, so data entered against them is lost — which is
why step 1 exists. For anything beyond a schema problem, restore the dump:

```bash
mysql -u root -p "$DB" < /root/pre-hire-deploy-<timestamp>.sql
php artisan cache:clear && systemctl restart php-fpm
```
