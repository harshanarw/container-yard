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
        return view('settings.company.index', compact('settings', 'currencies', 'countries'));
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
            'enable_gatepass_whatsapp' => ['nullable', 'boolean'],
            'app_base_url'             => ['nullable', 'string', 'max:255'],
            'logo'                     => ['nullable', 'image', 'max:2048'],
            'icon'            => ['nullable', 'mimes:jpg,jpeg,png,ico,svg,webp', 'max:512'],
            'product_icon'    => ['nullable', 'mimes:jpg,jpeg,png,ico,svg,webp', 'max:512'],
        ]);

        $settings = CompanySetting::current();
        $data = collect($validated)->except(['logo', 'icon', 'product_icon'])->toArray();
        $data['enable_digital_approvals'] = $request->boolean('enable_digital_approvals');
        $data['enable_guard_post']        = $request->boolean('enable_guard_post');
        $data['enforce_export_booking']   = $request->boolean('enforce_export_booking');
        $data['enforce_reefer_pti']       = $request->boolean('enforce_reefer_pti');
        $data['enable_gatepass_whatsapp'] = $request->boolean('enable_gatepass_whatsapp');

        // Normalise the system base URL: blank → null; add https:// if the
        // operator typed a bare host; drop any trailing slash.
        $base = trim((string) $request->input('app_base_url'));
        if ($base === '') {
            $data['app_base_url'] = null;
        } else {
            if (! preg_match('~^https?://~i', $base)) {
                $base = 'https://' . $base;
            }
            $data['app_base_url'] = rtrim($base, '/');
        }

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('company', 'public');
        }

        if ($request->hasFile('icon')) {
            if ($settings->icon_path) {
                Storage::disk('public')->delete($settings->icon_path);
            }
            $data['icon_path'] = $request->file('icon')->store('company', 'public');
        }

        if ($request->hasFile('product_icon')) {
            if ($settings->product_icon_path) {
                Storage::disk('public')->delete($settings->product_icon_path);
            }
            $data['product_icon_path'] = $request->file('product_icon')->store('company', 'public');
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
            Storage::disk('public')->delete($settings->logo_path);
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
            Storage::disk('public')->delete($settings->icon_path);
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
            Storage::disk('public')->delete($settings->product_icon_path);
            $settings->update(['product_icon_path' => null]);
            CompanySetting::flushCache();
        }

        return back()->with('success', 'Product icon removed.');
    }
}
