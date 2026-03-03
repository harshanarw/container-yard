<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    /**
     * Default settings stored in config or a simple key-value table.
     * Extend this to use a `settings` DB table if needed.
     */
    private array $defaults = [
        'yard_name'        => 'Container Yard Management System',
        'yard_address'     => '',
        'yard_phone'       => '',
        'yard_email'       => '',
        'yard_capacity'    => 440,
        'default_currency' => 'LKR',
        'default_tax'      => 0,
        'free_days'        => 7,
        'invoice_prefix'   => 'INV',
        'estimate_prefix'  => 'RE',
        'inquiry_prefix'   => 'INQ',
        'timezone'         => 'Asia/Kuala_Lumpur',
    ];

    public function index()
    {
        $settings = collect($this->defaults)->mapWithKeys(
            fn ($value, $key) => [$key => config("yard.{$key}", $value)]
        );

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'yard_name'        => ['required', 'string', 'max:255'],
            'yard_address'     => ['nullable', 'string'],
            'yard_phone'       => ['nullable', 'string', 'max:20'],
            'yard_email'       => ['nullable', 'email'],
            'yard_capacity'    => ['required', 'integer', 'min:1'],
            'default_currency' => ['required', 'in:LKR,USD,SGD'],
            'default_tax'      => ['required', 'numeric', 'min:0', 'max:100'],
            'free_days'        => ['required', 'integer', 'min:0'],
            'timezone'         => ['required', 'string'],
        ]);

        // Persist to .env or a settings table — here we write to config cache
        foreach ($validated as $key => $value) {
            config(["yard.{$key}" => $value]);
        }

        Artisan::call('config:cache');

        return back()->with('success', 'Settings saved successfully.');
    }
}
