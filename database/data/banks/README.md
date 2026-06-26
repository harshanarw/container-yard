# Country bank datasets

Default bank lists seeded into the **Bank master** (`banks` table) by
`Database\Seeders\BankSeeder`, one file per country, named by its
ISO 3166-1 alpha-2 code (e.g. `LK.php`, `AE.php`, `IN.php`).

## How the right file is chosen

`BankSeeder` resolves the deployment country via
`App\Support\DeploymentCountry::iso2()`:

1. `CompanySetting.country_id` (runtime, authoritative)
2. `config('localization.country')` / `APP_COUNTRY` env (install-time)
3. `LK` (fallback)

It then loads `database/data/banks/{ISO2}.php`. If no file exists for the
resolved country, the seeder logs a notice and skips — the master is left
empty for the admin to populate (no wrong-country data is seeded).

## File format

Return an array of rows: `[name, short_name, swift_code, local_code]`

```php
return [
    ['Example Bank PLC', 'EBP', 'EXMPXXYY', '0012'],
    // ...
];
```

- `swift_code` — BIC (international). Optional; verify before use.
- `local_code` — the country's national clearing/routing identifier
  (e.g. CBSL code, India IFSC, UK Sort Code, US ABA routing). Optional.

## Adding a new country

1. Add `database/data/banks/{ISO2}.php` (this folder), **or** skip the file
   and CSV-import the list from **Masters → Banks** after deploy.
2. Set `APP_COUNTRY={ISO2}` (and/or the country in Company Settings).
3. Run `php artisan db:seed --class=BankSeeder`.

Seeding is idempotent (`updateOrCreate` on name + country), so it is safe
to re-run and multiple countries can coexist in one master.
