<?php

namespace App\Support;

use App\Models\CompanySetting;
use App\Models\Country;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the country this instance is deployed for, via a priority chain:
 *
 *   1. CompanySetting.country_id  — authoritative once the deployment is configured
 *   2. config('localization.country') / APP_COUNTRY env — install/bootstrap value
 *   3. 'LK' — final fallback so nothing ever breaks
 *
 * CompanySetting wins at runtime; the env value carries the very first seed
 * before any settings row exists. They hand off rather than conflict.
 */
class DeploymentCountry
{
    /** ISO 3166-1 alpha-2 code, upper-cased. */
    public static function iso2(): string
    {
        // 1. CompanySetting (runtime authoritative) — guarded for early seed/migration
        try {
            if (Schema::hasTable('company_settings')) {
                $settings = CompanySetting::current();
                if ($settings && $settings->country_id) {
                    $iso = Country::whereKey($settings->country_id)->value('iso2');
                    if ($iso) {
                        return strtoupper($iso);
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through to config
        }

        // 2. config / env
        $configured = config('localization.country');
        if (!empty($configured)) {
            return strtoupper($configured);
        }

        // 3. default
        return 'LK';
    }

    /** countries.id for the resolved deployment country, or null if not found. */
    public static function id(): ?int
    {
        return Country::where('iso2', self::iso2())->value('id');
    }
}
