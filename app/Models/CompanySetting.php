<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    protected $fillable = [
        'company_name',
        'company_prefix',
        'tagline',
        'address',
        'city',
        'country',
        'country_id',
        'telephone',
        'email',
        'website',
        'vat_number',
        'tin_number',
        'ird_sequence_reset',
        'default_currency_code',
        'logo_path',
        'icon_path',
        'product_icon_path',
        'tax1_label',
        'tax2_label',
        'software_provider',
        // System Settings
        'yard_capacity',
        'max_storage_mb',
        'enforce_storage_limit',
        'free_storage_days',
        'timezone',
        'prefix_invoice',
        'prefix_sh_invoice',
        'prefix_survey',
        'prefix_estimate',
        'prefix_gate_in',
        'prefix_gate_out',
        'default_tax_rate',
        'surcharge_overtime',
        'surcharge_night',
        'enable_digital_approvals',
        'enable_guard_post',
        'enforce_export_booking',
        'enforce_reefer_pti',
        'require_seal_for_laden',
        'require_ot_receipt',
        'enable_gatepass_whatsapp',
        'guardpost_warn_no_capture',
        'app_base_url',
        'mr_dimension_uom',
        // Gate Pass Defaults
        'default_gate_in_format',
        'default_gate_out_format',
        // Finance Document Prefixes
        'prefix_receipt',
        'prefix_voucher',
        'prefix_journal',
        'prefix_supplier_invoice',
    ];

    protected $casts = [
        'enable_digital_approvals' => 'boolean',
        'enable_guard_post'        => 'boolean',
        'enforce_export_booking'   => 'boolean',
        'enforce_reefer_pti'       => 'boolean',
        'require_seal_for_laden'   => 'boolean',
        'require_ot_receipt'       => 'boolean',
        'enable_gatepass_whatsapp' => 'boolean',
        'guardpost_warn_no_capture' => 'boolean',
        'enforce_storage_limit'    => 'boolean',
    ];

    public static function current(): static
    {
        // Under Dusk the app runs as a separate long-lived server process, so a
        // cached settings value can go stale mid-run (a test changing a setting
        // can't bust the server's cache). Read fresh in that env only; cache
        // normally everywhere else (production and the PHPUnit "testing" env).
        if (app()->environment('dusk.local')) {
            return static::firstOrCreate([], ['company_name' => 'Container Yard Management']);
        }

        return Cache::remember('company_settings', 3600, fn () => static::firstOrCreate([], ['company_name' => 'Container Yard Management']));
    }

    public static function flushCache(): void
    {
        Cache::forget('company_settings');
    }

    /** Base / reporting currency code (the books are kept in this currency). */
    public static function baseCurrency(): string
    {
        return strtoupper(static::current()?->default_currency_code ?: 'LKR');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? Storage::url($this->icon_path) : null;
    }

    public function getProductIconUrlAttribute(): ?string
    {
        return $this->product_icon_path ? Storage::url($this->product_icon_path) : null;
    }

    /**
     * The operator-pinned public base URL for this system, normalised to
     * scheme://host[:port] with no trailing slash, or null when unset. Used to
     * force the URL root for ALL generated links (see App\Support\BaseUrl and
     * the ForceBaseUrl middleware) so proxy/subdomain host detection can't send
     * out links on the wrong domain.
     */
    public function appBaseUrl(): ?string
    {
        $base = trim((string) $this->app_base_url);
        if ($base === '') {
            return null;
        }

        // Tolerate a bare host saved without a scheme.
        if (! preg_match('~^https?://~i', $base)) {
            $base = 'https://' . $base;
        }

        $parts = parse_url(rtrim($base, '/'));
        if (empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host   = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $scheme . '://' . $host;
    }

    /**
     * Absolute URL for a driver gate-pass short link (/g/{code}). With the URL
     * root forced globally from app_base_url, a plain route() already carries
     * the correct host — no per-link handling needed.
     */
    public function gatePassUrl(string $code): string
    {
        return route('gp.short', $code);
    }
}
