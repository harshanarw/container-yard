<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingController extends Controller
{
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
        return view('settings.company.index', compact('settings', 'currencies'));
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
            'company_name' => ['required', 'string', 'max:200'],
            'tagline'      => ['nullable', 'string', 'max:200'],
            'address'      => ['nullable', 'string'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:100'],
            'telephone'    => ['nullable', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:200'],
            'website'      => ['nullable', 'string', 'max:200'],
            'vat_number'   => ['nullable', 'string', 'max:100'],
            'tin_number'   => ['nullable', 'string', 'max:100'],
            'logo'            => ['nullable', 'image', 'max:2048'],
            'icon'            => ['nullable', 'mimes:jpg,jpeg,png,ico,svg,webp', 'max:512'],
            'product_icon'    => ['nullable', 'mimes:jpg,jpeg,png,ico,svg,webp', 'max:512'],
        ]);

        $settings = CompanySetting::current();
        $data = collect($validated)->except(['logo', 'icon', 'product_icon'])->toArray();

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
