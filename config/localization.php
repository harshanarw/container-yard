<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment country
    |--------------------------------------------------------------------------
    |
    | The ISO 3166-1 alpha-2 code of the country this instance is deployed for.
    | Used at install/seed time (before Company Settings exist) to pick the
    | correct country-specific master data, e.g. the bank list seeded by
    | BankSeeder from database/data/banks/{ISO2}.php.
    |
    | At runtime, CompanySetting.country_id takes precedence over this value;
    | see App\Support\DeploymentCountry.
    |
    */

    'country' => env('APP_COUNTRY', 'LK'),

];
