<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Holiday;
use App\Models\OtTariffRule;
use App\Models\OtTariffVersion;
use App\Models\WorkingHourSet;
use App\Services\Overtime\OvertimeRuleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Overtime setup hub: one screen that shows which working-hour set, holiday
 * calendar and tariff version the OT engine is actually reading, flags the gaps
 * that would misbill or block the gate, and lets an admin dry-run any date/time
 * through the resolver before trusting the configuration.
 */
class OtSetupController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ot.settings.view');
    }

    public function index()
    {
        $today    = Carbon::today();
        $resolved = WorkingHourSet::resolved();

        $effective = OtTariffVersion::orderByDesc('effective_from')->get()
            ->first(fn (OtTariffVersion $v) => $v->isEffectiveOn($today));

        return view('overtime.setup.index', [
            'policyOn'      => (bool) CompanySetting::current()->require_ot_receipt,
            'workingSet'    => $resolved,
            'workingDays'   => $resolved ? $resolved->daysByName() : collect(),
            'effective'     => $effective,
            'ruleCount'     => $effective ? $effective->rules()->where('active', true)->count() : 0,
            'versionCount'  => OtTariffVersion::count(),
            'holidaysYear'  => Holiday::whereYear('holiday_date', $today->year)->where('active', true)->count(),
            'holidaysNext'  => Holiday::where('active', true)->whereDate('holiday_date', '>=', $today)
                                  ->orderBy('holiday_date')->limit(5)->get(),
            'issues'        => $this->healthCheck($resolved, $effective),
        ]);
    }

    /** AJAX: dry-run a date/time through the OT engine and show what it would decide. */
    public function preview(Request $request, OvertimeRuleResolver $resolver)
    {
        $v = $request->validate([
            'date'          => ['required', 'date'],
            'time'          => ['required', 'date_format:H:i'],
            'movement_type' => ['nullable', 'in:gate_in,gate_out'],
        ]);

        // Illuminate\Support\Carbon (not the base class) — the resolver hints the subclass.
        $at      = Carbon::parse($v['date'] . ' ' . $v['time']);
        $summary = $resolver->resolve($at, $v['movement_type'] ?? 'gate_in');

        return response()->json([
            'at'            => $at->format('D, d M Y H:i'),
            'day_category'  => $summary['day_category'],
            'category_label' => OtTariffRule::DAY_CATEGORIES[$summary['day_category']] ?? $summary['day_category'],
            'within_normal' => $summary['within_normal'],
            'is_overtime'   => $summary['is_overtime'],
            'unconfigured'  => $summary['unconfigured'],
            'holiday'       => Holiday::where('active', true)
                                  ->whereDate('holiday_date', $at->toDateString())->value('holiday_name'),
            'windows'       => $summary['windows']->map(fn ($w) => [
                'rule'       => $w['rule']->rule_code,
                'label'      => $w['rule']->display_name,
                'period'     => strtoupper($w['rule']->period_code),
                'rate'       => number_format((float) $w['rule']->rate_amount, 2),
                'currency'   => $w['rule']->currency,
                'valid_from' => $w['valid_from']->format('d M Y H:i'),
                'valid_to'   => $w['valid_to']->format('d M Y H:i'),
            ])->values(),
        ]);
    }

    /**
     * Configuration gaps that change behaviour at the gate, worst first. Each is
     * something an operator would otherwise only discover when a gate-in fails.
     */
    private function healthCheck(?WorkingHourSet $set, ?OtTariffVersion $effective): array
    {
        $issues = [];

        if (! $set) {
            $issues[] = [
                'level' => 'danger',
                'text'  => 'No active working-hour set. Without one the engine cannot tell normal hours from overtime, so every movement counts as overtime.',
                'route' => 'overtime.working-hours.index',
                'cta'   => 'Set working hours',
            ];
        } elseif (! $set->is_default) {
            $issues[] = [
                'level' => 'warning',
                'text'  => "No set is flagged default — the engine fell back to \"{$set->name}\". Flag one explicitly so the choice is not order-dependent.",
                'route' => 'overtime.working-hours.index',
                'cta'   => 'Flag a default',
            ];
        }

        if (! $effective) {
            $issues[] = [
                'level' => 'danger',
                'text'  => 'No tariff version is effective today. Out-of-hours movements will resolve as "unconfigured", which blocks the gate-in when enforcement is on.',
                'route' => 'overtime.tariffs.index',
                'cta'   => 'Set up a tariff',
            ];
        } elseif ($effective->rules()->where('active', true)->count() === 0) {
            $issues[] = [
                'level' => 'danger',
                'text'  => "Tariff version {$effective->version_code} is effective but has no active rate rules, so no overtime charge can be quoted.",
                'route' => 'overtime.tariffs.show',
                'param' => $effective,
                'cta'   => 'Add rate rules',
            ];
        }

        if (Holiday::where('active', true)->whereDate('holiday_date', '>=', Carbon::today())->doesntExist()) {
            $issues[] = [
                'level' => 'warning',
                'text'  => 'No upcoming holidays are configured. Holidays that are missing bill at weekday/Saturday rates instead of the holiday rate.',
                'route' => 'overtime.holidays.index',
                'cta'   => 'Open the calendar',
            ];
        }

        return $issues;
    }
}
