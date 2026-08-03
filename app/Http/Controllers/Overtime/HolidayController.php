<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Holiday calendar maintenance. A mercantile holiday overrides the weekly working
 * hours (closed / custom) and bills under the Sunday & Mercantile Holiday OT
 * category, so this is the second input to the overtime decision.
 */
class HolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ot.settings.view')->only(['index']);
        $this->middleware('can:ot.settings.edit')->only(['store', 'update', 'toggle', 'destroy']);
    }

    public function index(Request $request)
    {
        $year = (int) ($request->input('year') ?: now()->year);

        $holidays = Holiday::whereYear('holiday_date', $year)
            ->orderBy('holiday_date')
            ->get();

        // Years that actually have entries, plus a window around today, so the
        // operator can always reach next year to set it up.
        $years = Holiday::selectRaw('DISTINCT YEAR(holiday_date) AS y')
            ->orderBy('y')->pluck('y')
            ->merge([now()->year - 1, now()->year, now()->year + 1, $year])
            ->unique()->sort()->values();

        return view('overtime.holidays.index', [
            'year'      => $year,
            'years'     => $years,
            'holidays'  => $holidays,
            'byDate'    => $holidays->keyBy(fn (Holiday $h) => $h->holiday_date->toDateString()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $holiday = Holiday::create($data);

        return redirect()->route('overtime.holidays.index', ['year' => $holiday->holiday_date->year])
            ->with('success', "\"{$holiday->holiday_name}\" added to the holiday calendar.");
    }

    public function update(Request $request, Holiday $holiday)
    {
        $data = $this->validated($request, $holiday);

        $holiday->update($data);

        return redirect()->route('overtime.holidays.index', ['year' => $holiday->holiday_date->year])
            ->with('success', "\"{$holiday->holiday_name}\" updated.");
    }

    public function toggle(Holiday $holiday)
    {
        $holiday->update(['active' => ! $holiday->active]);

        return back()->with('success',
            "\"{$holiday->holiday_name}\" " . ($holiday->active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(Holiday $holiday)
    {
        $name = $holiday->holiday_name;
        $year = $holiday->holiday_date->year;
        $holiday->delete();

        return redirect()->route('overtime.holidays.index', ['year' => $year])
            ->with('success', "\"{$name}\" removed from the holiday calendar.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validated(Request $request, ?Holiday $existing = null): array
    {
        $data = $request->validate([
            'holiday_date' => [
                'required', 'date',
                Rule::unique('holidays', 'holiday_date')->ignore($existing?->id),
            ],
            'holiday_name'             => ['required', 'string', 'max:150'],
            'holiday_type'             => ['required', Rule::in(array_keys(Holiday::TYPES))],
            'is_mercantile'            => ['sometimes', 'boolean'],
            'working_hour_override'    => ['required', Rule::in(array_keys(Holiday::OVERRIDES))],
            'custom_start_time'        => ['nullable', 'date_format:H:i'],
            'custom_end_time'          => ['nullable', 'date_format:H:i'],
            'ot_day_category_override' => ['nullable', Rule::in(array_keys(Holiday::DAY_CATEGORY_OVERRIDES))],
            'active'                   => ['sometimes', 'boolean'],
            'remarks'                  => ['nullable', 'string', 'max:500'],
        ], [
            'holiday_date.unique' => 'That date is already in the holiday calendar — edit the existing entry instead.',
        ]);

        // Custom hours are only meaningful (and only read by the resolver) when the
        // override is "custom"; anything else discards them to avoid stale times.
        if ($data['working_hour_override'] === 'custom') {
            if (empty($data['custom_start_time']) || empty($data['custom_end_time'])) {
                throw ValidationException::withMessages([
                    'custom_start_time' => 'Custom hours need both a start and an end time.',
                ]);
            }
            if ($data['custom_end_time'] <= $data['custom_start_time']) {
                throw ValidationException::withMessages([
                    'custom_end_time' => 'Custom hours must end after they start.',
                ]);
            }
        } else {
            $data['custom_start_time'] = null;
            $data['custom_end_time']   = null;
        }

        $data['holiday_date']  = Carbon::parse($data['holiday_date'])->toDateString();
        $data['is_mercantile'] = $request->boolean('is_mercantile');
        $data['active']        = $request->boolean('active');
        // Nullable fields are absent from the validated set when the form omits
        // them entirely (an API client, or the "derive from type" blank option),
        // so read through ?? rather than indexing straight in.
        $data['ot_day_category_override'] = ($data['ot_day_category_override'] ?? null) ?: null;

        return $data;
    }
}
