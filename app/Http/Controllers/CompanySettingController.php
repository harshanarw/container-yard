<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
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
        $settings = CompanySetting::current();
        return view('settings.company.index', compact('settings'));
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
            'logo'         => ['nullable', 'image', 'max:2048'],
            'icon'         => ['nullable', 'image', 'max:512', 'dimensions:max_width=512,max_height=512'],
        ]);

        $settings = CompanySetting::current();
        $data = collect($validated)->except(['logo', 'icon'])->toArray();

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
}
