<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private function authorise(): void
    {
        if (!auth()->user()->isSuperUser()) {
            abort(403, 'Administrator access required.');
        }
    }

    public function index()
    {
        $this->authorise();
        $settings = CompanySetting::current();
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $this->authorise();

        $validated = $request->validate([
            // Operational
            'yard_capacity'     => ['required', 'integer', 'min:1', 'max:99999'],
            'free_storage_days' => ['required', 'integer', 'min:0', 'max:365'],
            'timezone'          => ['required', 'string', 'max:100'],

            // Prefixes
            'prefix_invoice'    => ['required', 'string', 'max:20', 'alpha_num'],
            'prefix_sh_invoice' => ['required', 'string', 'max:20', 'alpha_num'],
            'prefix_survey'     => ['required', 'string', 'max:20', 'alpha_num'],
            'prefix_estimate'   => ['required', 'string', 'max:20', 'alpha_num'],
            'prefix_gate_in'    => ['required', 'string', 'max:20', 'alpha_num'],
            'prefix_gate_out'   => ['required', 'string', 'max:20', 'alpha_num'],

            // Billing defaults
            'default_tax_rate'  => ['required', 'numeric', 'min:0', 'max:100'],
            'surcharge_overtime' => ['required', 'numeric', 'min:0', 'max:500'],
            'surcharge_night'   => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $settings = CompanySetting::current();
        $settings->update($validated);
        CompanySetting::flushCache();

        return back()->with('success', 'System settings saved successfully.');
    }
}
