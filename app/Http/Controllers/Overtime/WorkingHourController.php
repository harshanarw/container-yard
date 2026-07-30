<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Models\WeeklyWorkingHour;
use App\Models\WorkingHourSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin maintenance for the normal working-hour master. A set carries one row per
 * weekday; overtime applies outside those windows, so this screen is what decides
 * when the OT tariff kicks in. The set flagged default (and active) is the one
 * OvertimeRuleResolver reads.
 */
class WorkingHourController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ot.settings.view')->only(['index', 'show']);
        $this->middleware('can:ot.settings.edit')->only([
            'create', 'store', 'edit', 'update', 'setDefault', 'destroy',
        ]);
    }

    public function index()
    {
        return view('overtime.working-hours.index', [
            'sets'     => WorkingHourSet::with('days')->orderByDesc('is_default')->orderBy('name')->get(),
            'resolved' => WorkingHourSet::resolved(),
        ]);
    }

    public function create()
    {
        return view('overtime.working-hours.edit', [
            'set'  => new WorkingHourSet(['status' => 'active', 'effective_from' => now()->toDateString()]),
            'days' => $this->defaultDayGrid(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $set = DB::transaction(function () use ($data) {
            $set = WorkingHourSet::create($data['set'] + ['created_by' => Auth::id()]);
            $this->syncDays($set, $data['days']);
            $this->enforceSingleDefault($set);

            return $set;
        });

        return redirect()->route('overtime.working-hours.edit', $set)
            ->with('success', "Working-hour set \"{$set->name}\" created.");
    }

    public function edit(WorkingHourSet $workingHourSet)
    {
        $existing = $workingHourSet->daysByName();

        // Always render a full Mon→Sun grid, even if a day row is missing.
        $days = collect($this->defaultDayGrid())
            ->map(fn ($row, $day) => $existing->has($day) ? $this->rowFrom($existing[$day]) : $row)
            ->all();

        return view('overtime.working-hours.edit', [
            'set'  => $workingHourSet,
            'days' => $days,
        ]);
    }

    public function update(Request $request, WorkingHourSet $workingHourSet)
    {
        $data = $this->validated($request, $workingHourSet);

        DB::transaction(function () use ($workingHourSet, $data) {
            $workingHourSet->update($data['set']);
            $this->syncDays($workingHourSet, $data['days']);
            $this->enforceSingleDefault($workingHourSet);
        });

        return redirect()->route('overtime.working-hours.index')
            ->with('success', "Working-hour set \"{$workingHourSet->name}\" saved.");
    }

    public function setDefault(WorkingHourSet $workingHourSet)
    {
        if ($workingHourSet->status !== 'active') {
            return back()->with('error', 'Only an active set can be made the default.');
        }

        DB::transaction(function () use ($workingHourSet) {
            $workingHourSet->update(['is_default' => true]);
            $this->enforceSingleDefault($workingHourSet);
        });

        return back()->with('success', "\"{$workingHourSet->name}\" is now the default working-hour set.");
    }

    public function destroy(WorkingHourSet $workingHourSet)
    {
        // Deleting the set the resolver reads would leave every day "closed", making
        // every gate-in overtime. Point the operator at another set first.
        if ($workingHourSet->isResolved()) {
            return back()->with('error',
                'This is the working-hour set currently in use. Make another active set the default before deleting it.');
        }

        $name = $workingHourSet->name;
        $workingHourSet->delete(); // weekly_working_hours cascade

        return redirect()->route('overtime.working-hours.index')
            ->with('success', "Working-hour set \"{$name}\" deleted.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array{set: array, days: array} */
    private function validated(Request $request, ?WorkingHourSet $existing = null): array
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'effective_to'   => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status'         => ['required', Rule::in(array_keys(WorkingHourSet::STATUSES))],
            'is_default'     => ['sometimes', 'boolean'],

            'days'                              => ['required', 'array'],
            'days.*.is_regular_working_day'      => ['sometimes', 'boolean'],
            'days.*.normal_start_time'           => ['nullable', 'date_format:H:i'],
            'days.*.normal_end_time'             => ['nullable', 'date_format:H:i'],
            'days.*.after_hours_policy'          => ['required', Rule::in(array_keys(WeeklyWorkingHour::AFTER_HOURS_POLICIES))],
        ]);

        $days   = [];
        $errors = [];

        foreach (array_keys(WeeklyWorkingHour::DAYS) as $day) {
            $row     = $validated['days'][$day] ?? [];
            $regular = (bool) ($row['is_regular_working_day'] ?? false);
            $start   = $row['normal_start_time'] ?? null;
            $end     = $row['normal_end_time'] ?? null;

            if ($regular) {
                if (! $start || ! $end) {
                    $errors["days.{$day}.normal_start_time"] =
                        WeeklyWorkingHour::DAYS[$day] . ' is a working day, so it needs a start and end time (or untick it to mark the day closed).';
                } elseif ($end <= $start) {
                    $errors["days.{$day}.normal_end_time"] =
                        WeeklyWorkingHour::DAYS[$day] . ' must end after it starts.';
                }
            } else {
                // Closed day → the resolver treats any time as overtime.
                $start = $end = null;
            }

            $days[$day] = [
                'is_regular_working_day' => $regular,
                'normal_start_time'      => $start,
                'normal_end_time'        => $end,
                'after_hours_policy'     => $row['after_hours_policy'] ?? 'ot_required',
                'active'                 => true,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // A set with every day closed makes 100% of movements overtime — almost
        // certainly a mistake, and it silently blocks the whole yard.
        if (! collect($days)->contains(fn ($d) => $d['is_regular_working_day'])) {
            throw ValidationException::withMessages([
                'days.monday.is_regular_working_day' => 'At least one day must be a working day, otherwise every gate movement becomes overtime.',
            ]);
        }

        // Name uniqueness keeps the picker readable (the table has no unique index).
        $dupName = WorkingHourSet::where('name', $validated['name'])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($dupName) {
            throw ValidationException::withMessages(['name' => 'Another working-hour set already uses this name.']);
        }

        return [
            'set' => [
                'name'           => $validated['name'],
                'effective_from' => $validated['effective_from'] ?? null,
                'effective_to'   => $validated['effective_to'] ?? null,
                'status'         => $validated['status'],
                'is_default'     => $request->boolean('is_default'),
            ],
            'days' => $days,
        ];
    }

    private function syncDays(WorkingHourSet $set, array $days): void
    {
        foreach ($days as $day => $attributes) {
            WeeklyWorkingHour::updateOrCreate(
                ['working_hour_set_id' => $set->id, 'day_of_week' => $day],
                $attributes
            );
        }
    }

    /** Exactly one default set — the resolver picks the default first. */
    private function enforceSingleDefault(WorkingHourSet $set): void
    {
        if (! $set->fresh()->is_default) {
            return;
        }

        WorkingHourSet::where('id', '!=', $set->id)->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /** Mon–Fri 08:00–17:00, Sat 08:00–13:00, Sun closed — the seeded default shape. */
    private function defaultDayGrid(): array
    {
        $grid = [];
        foreach (array_keys(WeeklyWorkingHour::DAYS) as $day) {
            $grid[$day] = match ($day) {
                'saturday' => $this->row(true, '08:00', '13:00'),
                'sunday'   => $this->row(false, null, null),
                default    => $this->row(true, '08:00', '17:00'),
            };
        }

        return $grid;
    }

    private function row(bool $regular, ?string $start, ?string $end): array
    {
        return [
            'is_regular_working_day' => $regular,
            'normal_start_time'      => $start,
            'normal_end_time'        => $end,
            'after_hours_policy'     => 'ot_required',
        ];
    }

    /** DB row → form row (TIME comes back as H:i:s; the time input wants H:i). */
    private function rowFrom(WeeklyWorkingHour $day): array
    {
        return [
            'is_regular_working_day' => (bool) $day->is_regular_working_day,
            'normal_start_time'      => $day->normal_start_time ? substr((string) $day->normal_start_time, 0, 5) : null,
            'normal_end_time'        => $day->normal_end_time ? substr((string) $day->normal_end_time, 0, 5) : null,
            'after_hours_policy'     => $day->after_hours_policy,
        ];
    }
}
