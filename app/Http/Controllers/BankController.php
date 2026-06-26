<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanySetting;
use App\Models\Country;
use App\Support\DeploymentCountry;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.banks.view')->only(['index', 'export']);
        $this->middleware('can:masters.banks.create')->only(['store', 'import']);
        $this->middleware('can:masters.banks.edit')->only(['update', 'toggleActive', 'reorder']);
        $this->middleware('can:masters.banks.delete')->only(['destroy']);
    }

    public function index()
    {
        $banks            = Bank::with('countryInfo')->orderBy('sort_order')->orderBy('name')->get();
        $countries        = Country::forSelect();
        $defaultCountryId = CompanySetting::current()?->country_id;

        return view('masters.banks.index', compact('banks', 'countries', 'defaultCountryId'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['sort_order'] = Bank::max('sort_order') + 1;
        $data['is_active']  = true;

        Bank::create($data);

        return back()->with('success', "Bank \"{$data['name']}\" added.");
    }

    public function update(Request $request, Bank $bank)
    {
        $data = $this->validateData($request, $bank->id);
        $bank->update($data);

        return back()->with('success', "Bank \"{$bank->name}\" updated.");
    }

    public function toggleActive(Bank $bank)
    {
        $bank->update(['is_active' => !$bank->is_active]);
        $state = $bank->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$bank->name} {$state}.");
    }

    public function destroy(Bank $bank)
    {
        if ($bank->bankAccounts()->exists()) {
            return back()->with('error', 'Cannot delete: this bank is linked to one or more bank accounts.');
        }

        $bank->delete();

        return back()->with('success', "Bank \"{$bank->name}\" deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:banks,id'],
        ]);

        foreach ($request->order as $position => $id) {
            Bank::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:banks,name' . ($ignoreId ? ",{$ignoreId}" : '');

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150', $unique],
            'short_name' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'local_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        ]);

        $data['swift_code'] = $data['swift_code'] ? strtoupper($data['swift_code']) : null;

        return $data;
    }

    /**
     * Bulk import banks from a CSV. Header row required; recognised columns:
     * name, short_name, swift_code, local_code, country_iso.
     * Rows without a country_iso are assigned to the deployment country.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $defaultCountryId = DeploymentCountry::id();
        $header = null;
        $created = 0; $updated = 0; $skipped = 0; $line = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($line === 1) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                continue;
            }

            if ($header === null || count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $data = array_combine($header, array_pad($row, count($header), null)) ?: [];
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') { $skipped++; continue; }

            $countryId = $defaultCountryId;
            if (!empty($data['country_iso'])) {
                $resolved = Country::where('iso2', strtoupper(trim((string) $data['country_iso'])))->value('id');
                if ($resolved) { $countryId = $resolved; }
            }

            $swift = trim((string) ($data['swift_code'] ?? ''));

            $bank = Bank::updateOrCreate(
                ['name' => $name, 'country_id' => $countryId],
                [
                    'short_name' => trim((string) ($data['short_name'] ?? '')) ?: null,
                    'swift_code' => $swift !== '' ? strtoupper($swift) : null,
                    'local_code' => trim((string) ($data['local_code'] ?? '')) ?: null,
                    'is_active'  => true,
                ]
            );

            if ($bank->wasRecentlyCreated) {
                $bank->update(['sort_order' => Bank::max('sort_order') + 1]);
                $created++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        $msg = "Import complete: {$created} added, {$updated} updated"
            . ($skipped ? ", {$skipped} skipped" : '') . '.';

        return back()->with('success', $msg);
    }

    /** Export the full bank master as CSV (also serves as the import template). */
    public function export()
    {
        $banks   = Bank::with('countryInfo')->orderBy('sort_order')->orderBy('name')->get();
        $columns = ['name', 'short_name', 'swift_code', 'local_code', 'country_iso', 'is_active'];

        return response()->streamDownload(function () use ($banks, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($banks as $b) {
                fputcsv($out, [
                    $b->name,
                    $b->short_name,
                    $b->swift_code,
                    $b->local_code,
                    $b->countryInfo?->iso2,
                    $b->is_active ? 1 : 0,
                ]);
            }
            fclose($out);
        }, 'banks.csv', ['Content-Type' => 'text/csv']);
    }
}
