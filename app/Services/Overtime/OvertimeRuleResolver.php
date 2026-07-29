<?php

namespace App\Services\Overtime;

use App\Models\Holiday;
use App\Models\OtTariffRule;
use App\Models\OtTariffVersion;
use App\Models\WeeklyWorkingHour;
use App\Models\WorkingHourSet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The overtime "brain": given a date/time it resolves the operational day category
 * (holiday → Sunday/mercantile → Saturday → weekday), whether the moment is inside
 * normal working hours, and which tariff rules (with their validity windows) apply
 * — including next-day rollover so an early-morning gate-in matches the previous
 * day's extended (B) window.
 *
 * All rates/windows come from the seeded masters — nothing is hard-coded here.
 */
class OvertimeRuleResolver
{
    /** day_of_week => WeeklyWorkingHour, for the active default set. */
    private ?Collection $weekly = null;

    /** Resolve the operational day category for a date (priority per SRS §7.1). */
    public function resolveDayCategory(Carbon $date): string
    {
        $holiday = $this->holidayOn($date);
        if ($holiday) {
            if ($holiday->ot_day_category_override) {
                return $holiday->ot_day_category_override;
            }
            return $holiday->is_mercantile ? 'sunday_mercantile_holiday' : 'custom_holiday';
        }

        if ($date->isSunday()) {
            return 'sunday_mercantile_holiday';
        }
        if ($date->isSaturday()) {
            return 'saturday';
        }

        return 'weekday';
    }

    /** True when the datetime falls inside the normal (non-OT) working window. */
    public function isWithinNormalWorkingHours(Carbon $datetime): bool
    {
        $window = $this->normalWindow($datetime->copy()->startOfDay());
        if (! $window) {
            return false; // closed day (Sunday, holiday) → never "normal"
        }

        $t = $datetime->format('H:i:s');

        return $t >= $window['start'] && $t < $window['end'];
    }

    /** OT applies whenever the moment is outside normal working hours. */
    public function isOvertime(Carbon $datetime): bool
    {
        return ! $this->isWithinNormalWorkingHours($datetime);
    }

    /**
     * Tariff windows that cover the given datetime, each as
     * ['rule', 'operational_date', 'valid_from', 'valid_to']. Considers the date's
     * own rules and the previous day's next-day (B/extended) rules.
     */
    public function getApplicableWindows(Carbon $datetime, string $movementType = 'gate_in'): Collection
    {
        $out  = collect();
        $date = $datetime->copy()->startOfDay();

        foreach ($this->rulesForDate($date, $movementType) as $rule) {
            $win = $this->buildValidityWindow($rule, $date);
            if ($datetime->gte($win["from"]) && $datetime->lte($win["to"])) {
                $out->push($this->window($rule, $date, $win));
            }
        }

        // Early-morning rollover: a gate-in after midnight may be covered by the
        // previous operational day's extended (ends_next_day) window.
        $prev = $date->copy()->subDay();
        foreach ($this->rulesForDate($prev, $movementType) as $rule) {
            if (! $rule->ends_next_day) {
                continue;
            }
            $win = $this->buildValidityWindow($rule, $prev);
            if ($datetime->gte($win["from"]) && $datetime->lte($win["to"])) {
                $out->push($this->window($rule, $prev, $win));
            }
        }

        return $out;
    }

    /** All tariff rules offered for an operational date's day category (A + B). */
    public function getApplicableRules(Carbon $date, string $movementType = 'gate_in'): Collection
    {
        return $this->rulesForDate($date->copy()->startOfDay(), $movementType);
    }

    /** [from, to] validity window for a rule on an operational date (handles next-day). */
    public function buildValidityWindow(OtTariffRule $rule, Carbon $operationalDate): array
    {
        $from = $operationalDate->copy()->setTimeFromTimeString($rule->start_time);
        $to   = $operationalDate->copy()->setTimeFromTimeString($rule->end_time);
        if ($rule->ends_next_day) {
            $to->addDay();
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * One-call summary used by the receipt module and gate-in validator.
     * `unconfigured` = OT applies but no tariff rule covers the moment (e.g. the
     * 05:00–08:00 gap) → manual approval / custom rule required.
     */
    public function resolve(Carbon $datetime, string $movementType = 'gate_in'): array
    {
        $withinNormal = $this->isWithinNormalWorkingHours($datetime);
        $windows      = $withinNormal ? collect() : $this->getApplicableWindows($datetime, $movementType);

        return [
            'day_category'  => $this->resolveDayCategory($datetime->copy()->startOfDay()),
            'within_normal' => $withinNormal,
            'is_overtime'   => ! $withinNormal,
            'windows'       => $windows,
            'unconfigured'  => ! $withinNormal && $windows->isEmpty(),
        ];
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function rulesForDate(Carbon $date, string $movementType): Collection
    {
        $version = $this->activeVersion($date);
        if (! $version) {
            return collect();
        }

        $category = $this->resolveDayCategory($date);

        return OtTariffRule::where('ot_tariff_version_id', $version->id)
            ->where('active', true)
            ->where('day_category', $category)
            ->whereIn('movement_type', [$movementType, 'both'])
            ->orderBy('priority')
            ->get();
    }

    private function activeVersion(Carbon $date): ?OtTariffVersion
    {
        $d = $date->toDateString();

        return OtTariffVersion::where('active', true)
            ->where('approval_status', 'active')
            ->whereDate('effective_from', '<=', $d)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $d))
            ->orderByDesc('effective_from')
            ->first();
    }

    /** Normal working [start,end] (H:i:s) for a date, honoring holiday overrides. */
    private function normalWindow(Carbon $date): ?array
    {
        $holiday = $this->holidayOn($date);
        if ($holiday) {
            if ($holiday->working_hour_override === 'custom' && $holiday->custom_start_time && $holiday->custom_end_time) {
                return ['start' => $this->hms($holiday->custom_start_time), 'end' => $this->hms($holiday->custom_end_time)];
            }
            if ($holiday->working_hour_override !== 'normal') {
                return null; // closed
            }
            // 'normal' → fall through to the weekday configuration.
        }

        $day = $this->weeklyFor($date);
        if (! $day || ! $day->is_regular_working_day || ! $day->normal_start_time || ! $day->normal_end_time) {
            return null;
        }

        return ['start' => $this->hms($day->normal_start_time), 'end' => $this->hms($day->normal_end_time)];
    }

    private function weeklyFor(Carbon $date): ?WeeklyWorkingHour
    {
        if ($this->weekly === null) {
            $set = WorkingHourSet::where('is_default', true)->where('status', 'active')->first()
                ?? WorkingHourSet::where('status', 'active')->first();

            $this->weekly = $set
                ? WeeklyWorkingHour::where('working_hour_set_id', $set->id)->get()->keyBy('day_of_week')
                : collect();
        }

        return $this->weekly->get(strtolower($date->englishDayOfWeek));
    }

    private function holidayOn(Carbon $date): ?Holiday
    {
        return Holiday::where('active', true)->whereDate('holiday_date', $date->toDateString())->first();
    }

    private function window(OtTariffRule $rule, Carbon $operationalDate, array $win): array
    {
        return [
            'rule'             => $rule,
            'operational_date' => $operationalDate->copy(),
            'valid_from'       => $win['from'],
            'valid_to'         => $win['to'],
        ];
    }

    private function hms($time): string
    {
        return substr((string) $time, 0, 8) === (string) $time && strlen((string) $time) === 8
            ? (string) $time
            : Carbon::parse((string) $time)->format('H:i:s');
    }
}
