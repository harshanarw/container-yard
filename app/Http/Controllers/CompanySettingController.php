<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.company.view')->only(['index']);
        $this->middleware('can:settings.company.edit')->only(['update', 'setDefaultCurrency', 'deleteLogo', 'deleteIcon', 'deleteProductIcon']);
    }

    private function authorise(): void
    {
        if (!auth()->user()->isSystemAdmin()) {
            abort(403, 'System Administrator access required.');
        }
    }

    public function index()
    {
        $this->authorise();
        $settings   = CompanySetting::current();
        $currencies = Currency::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $countries  = Country::forSelect();
        $storageUsage = app(\App\Services\StorageUsageService::class)->summary();

        // Effective thresholds (settings merged over the shipped defaults), so
        // the screen shows what is actually in force rather than blanks.
        $mrThresholds = app(\App\Services\ContainerMrStatusService::class)->ageThresholds();

        return view('settings.company.index', compact(
            'settings', 'currencies', 'countries', 'storageUsage', 'mrThresholds'
        ));
    }

    public function setDefaultCurrency(Request $request)
    {
        $this->authorise();

        $validated = $request->validate([
            'currency_id' => ['required', 'exists:currencies,id'],
        ]);

        $currency = Currency::findOrFail($validated['currency_id']);

        if (!$currency->is_active) {
            return back()->with('error', 'Cannot set an inactive currency as the system default.');
        }

        Currency::setDefault($currency);

        return back()->with('success', "Default currency updated to {$currency->code} — {$currency->name}.");
    }

    public function update(Request $request)
    {
        $this->authorise();

        $validated = $request->validate([
            'company_name'   => ['required', 'string', 'max:200'],
            'company_prefix' => ['nullable', 'string', 'max:10', 'alpha_num'],
            'tagline'        => ['nullable', 'string', 'max:200'],
            'address'      => ['nullable', 'string'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country_id'   => ['nullable', 'integer', 'exists:countries,id'],
            'telephone'    => ['nullable', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:200'],
            'website'      => ['nullable', 'string', 'max:200'],
            'vat_number'        => ['nullable', 'string', 'max:100'],
            'tin_number'        => ['nullable', 'string', 'max:100'],
            'software_provider'        => ['nullable', 'string', 'max:200'],
            'ird_sequence_reset'       => ['nullable', 'string', 'in:continuous,monthly,yearly'],
            'enable_digital_approvals' => ['nullable', 'boolean'],
            'enable_guard_post'        => ['nullable', 'boolean'],
            'enforce_export_booking'   => ['nullable', 'boolean'],
            'enforce_reefer_pti'       => ['nullable', 'boolean'],
            'require_seal_for_laden'   => ['nullable', 'boolean'],
            'require_ot_receipt'       => ['nullable', 'boolean'],
            'enable_gatepass_whatsapp' => ['nullable', 'boolean'],
            'guardpost_warn_no_capture' => ['nullable', 'boolean'],
            'max_storage_mb'           => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'enforce_storage_limit'    => ['nullable', 'boolean'],
            'app_base_url'             => ['nullable', 'string', 'max:255'],
            // Overdue thresholds, status code => days. Blank means "don't flag
            // this stage"; 1..365 keeps a typo from flagging the whole yard or
            // nothing at all.
            'mr_age_thresholds'        => ['nullable', 'array'],
            'mr_age_thresholds.*'      => ['nullable', 'integer', 'min:1', 'max:365'],
            'logo'                     => ['nullable', 'image', 'max:2048'],
            'icon'            => ['nullable', 'mimes:jpg,jpeg,png,ico,svg,webp', 'max:512'],
            'product_icon'    => ['nullable', 'mimes:jpg,jpeg,png,ico,svg,webp', 'max:512'],
        ]);

        $settings = CompanySetting::current();
        $data = collect($validated)->except(['logo', 'icon', 'product_icon'])->toArray();
        // Absence means "the submitted form does not own this field", not "off".
        //
        // Three forms on this page post to this action — the logo, icon and
        // product-icon uploads each send only company_name and their file.
        // Assigning these unconditionally meant uploading a logo read nine
        // absent checkboxes as false, silently switching off guard post, seal
        // enforcement, reefer PTI and export-booking enforcement, and blanking
        // the base URL that emailed gate-pass links are built from.
        //
        // The main form now submits a hidden 0 alongside every checkbox, so an
        // unchecked box is still *present* and can still be turned off.
        foreach ([
            'enable_digital_approvals',
            'enable_guard_post',
            'enforce_export_booking',
            'enforce_reefer_pti',
            'require_seal_for_laden',
            'require_ot_receipt',
            'enable_gatepass_whatsapp',
            'guardpost_warn_no_capture',
            'enforce_storage_limit',
        ] as $flag) {
            if ($request->has($flag)) {
                $data[$flag] = $request->boolean($flag);
            }
        }

        // Normalise the system base URL: blank → null; add https:// if the
        // operator typed a bare host; drop any trailing slash.
        if ($request->has('app_base_url')) {
            $base = trim((string) $request->input('app_base_url'));

            if ($base === '') {
                $data['app_base_url'] = null;
            } else {
                if (! preg_match('~^https?://~i', $base)) {
                    $base = 'https://' . $base;
                }
                $data['app_base_url'] = rtrim($base, '/');
            }
        }

        // Only touched when the form that owns it was the one submitted.
        //
        // Several forms on this page post to this same action — the logo, icon
        // and product-icon uploads each send just company_name and their file.
        // Writing this key unconditionally would wipe every configured
        // threshold the next time someone uploaded a logo.
        if ($request->has('mr_age_thresholds')) {
            // Store only what the operator actually set, and only for stages
            // that exist. Blanks are dropped rather than saved, so a stage left
            // empty keeps falling back to the shipped default, and a status
            // added to the catalogue later works without anyone revisiting this
            // screen.
            $data['mr_age_thresholds'] = collect($request->input('mr_age_thresholds', []))
                ->filter(fn ($days, $code) => $days !== null && $days !== ''
                    && array_key_exists($code, \App\Support\MrStatusCatalogue::CATALOGUE))
                ->map(fn ($days) => (int) $days)
                ->all() ?: null;
        }

        $storage = app(\App\Services\StorageService::class);

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                $storage->delete('public', $settings->logo_path);
            }
            $data['logo_path'] = $storage->store($request->file('logo'), 'company', 'company', $settings);
        }

        if ($request->hasFile('icon')) {
            if ($settings->icon_path) {
                $storage->delete('public', $settings->icon_path);
            }
            $data['icon_path'] = $storage->store($request->file('icon'), 'company', 'company', $settings);
        }

        if ($request->hasFile('product_icon')) {
            if ($settings->product_icon_path) {
                $storage->delete('public', $settings->product_icon_path);
            }
            $data['product_icon_path'] = $storage->store($request->file('product_icon'), 'company', 'company', $settings);
        }

        $settings->update($data);
        CompanySetting::flushCache();

        return back()->with('success', 'Company settings saved successfully.');
    }

    public function deleteLogo()
    {
        $this->authorise();

        $settings = CompanySetting::current();
        if ($settings->logo_path) {
            app(\App\Services\StorageService::class)->delete('public', $settings->logo_path);
            $settings->update(['logo_path' => null]);
            CompanySetting::flushCache();
        }

        return back()->with('success', 'Logo removed.');
    }

    public function deleteIcon()
    {
        $this->authorise();

        $settings = CompanySetting::current();
        if ($settings->icon_path) {
            app(\App\Services\StorageService::class)->delete('public', $settings->icon_path);
            $settings->update(['icon_path' => null]);
            CompanySetting::flushCache();
        }

        return back()->with('success', 'Icon removed.');
    }

    public function deleteProductIcon()
    {
        $this->authorise();

        $settings = CompanySetting::current();
        if ($settings->product_icon_path) {
            app(\App\Services\StorageService::class)->delete('public', $settings->product_icon_path);
            $settings->update(['product_icon_path' => null]);
            CompanySetting::flushCache();
        }

        return back()->with('success', 'Product icon removed.');
    }
}
