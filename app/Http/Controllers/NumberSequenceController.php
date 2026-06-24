<?php

namespace App\Http\Controllers;

use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Http\Request;

class NumberSequenceController extends Controller
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
        $sequences = NumberSequence::orderBy('label')->get();
        return view('settings.number-sequences.index', compact('sequences'));
    }

    public function update(Request $request, NumberSequence $numberSequence)
    {
        $this->authorise();

        $validated = $request->validate([
            'label'              => ['required', 'string', 'max:100'],
            'prefix'             => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'use_company_prefix' => ['boolean'],
            'separator'          => ['required', 'string', 'size:1'],
            'date_format'        => ['nullable', 'in:Ym,Y,ym,yM'],
            'seq_padding'        => ['required', 'integer', 'min:3', 'max:9'],
            'reset_period'       => ['required', 'in:never,monthly,yearly'],
        ]);

        // Normalise: prefix always uppercase, use_company_prefix defaults to false if omitted
        $validated['prefix']             = strtoupper($validated['prefix']);
        $validated['use_company_prefix'] = $request->boolean('use_company_prefix');

        // Changing date_format or reset_period requires resetting the tracking period
        // so the counter doesn't carry over from a different period format.
        if ($validated['date_format']   !== $numberSequence->date_format ||
            $validated['reset_period']  !== $numberSequence->reset_period) {
            $validated['current_period'] = '';
        }

        $numberSequence->update($validated);

        return back()->with('success', "Sequence \"{$numberSequence->label}\" updated.");
    }

    public function resetCounter(NumberSequence $numberSequence)
    {
        $this->authorise();

        if (!auth()->user()->isSystemAdmin()) {
            abort(403, 'System Administrator access required to reset counters.');
        }

        $numberSequence->update([
            'last_number'    => 0,
            'current_period' => '',
        ]);

        return back()->with('success', "Counter for \"{$numberSequence->label}\" has been reset to 0.");
    }

    public function preview(NumberSequence $numberSequence, NumberSequenceService $service)
    {
        $this->authorise();

        return response()->json([
            'preview' => $service->preview($numberSequence->module_code),
        ]);
    }
}
