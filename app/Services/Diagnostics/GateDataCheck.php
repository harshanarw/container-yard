<?php

namespace App\Services\Diagnostics;

use App\Models\GateMovement;
use App\Services\Reporting\MovementVisits;

/**
 * Finds gate movements whose timestamps cannot be true.
 *
 * Three checks, all on gate times. Nothing about M&R, billing or anything else —
 * the point is a short list somebody can work through, not a dashboard of
 * everything that might be imperfect.
 *
 * **It only finds.** Corrections go through the movement edit screen, which
 * already syncs a changed gate-in date onto `containers` and `yard_storage` so
 * billing stays right, and which now refuses a correction that is still
 * contradictory.
 *
 * The same `>=` rule the gate validation uses decides what counts as reversed,
 * so a container in at 08:00 and out at 17:00 on one date is not a finding, and
 * neither is a pair recorded date-only where both ends store as `00:00:00`. If
 * the two ever disagreed, the screen would report problems the gate would not
 * let anyone fix.
 */
class GateDataCheck
{
    public const FUTURE_GATE_IN = 'future_gate_in';
    public const OUT_BEFORE_IN  = 'out_before_in';
    public const NO_GATE_IN     = 'no_gate_in';

    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            self::FUTURE_GATE_IN => 'Gate-in in the future',
            self::OUT_BEFORE_IN  => 'Out before In',
            self::NO_GATE_IN     => 'No gate-in recorded',
        ];
    }

    public static function label(string $check): string
    {
        return self::labels()[$check] ?? $check;
    }

    public function __construct(private MovementVisits $visits) {}

    /**
     * Every finding in the range, newest first.
     *
     * @param  array{from?:?string,to?:?string,customer_id?:?int}  $filters
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    public function findings(array $filters = [])
    {
        $movements = $this->movements($filters);
        $visits    = $this->visits->for($movements);

        $findings = $movements
            ->map(fn (GateMovement $m) => $this->check($m, $visits[$m->id] ?? null))
            ->filter();

        return $this->collapseToRootCause($findings)
            ->sortByDesc(fn ($f) => $f['at'])
            ->values();
    }

    /**
     * One row per container problem, not one per symptom.
     *
     * A future-dated arrival does not just sit there being wrong: it also stops
     * its own departure pairing, so the same typo produces a second finding
     * saying that departure has no arrival. Both disappear the moment the date
     * is fixed, so listing both is listing one problem twice — and on a screen
     * whose whole value is being a short list somebody can work through, that
     * matters.
     *
     * The live system has exactly this: `MEDU8724659` has an arrival dated
     * 7 September and a departure on 28 August, which is one mistyped month.
     *
     * @param  \Illuminate\Support\Collection<int,array<string,mixed>>  $findings
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    private function collapseToRootCause($findings)
    {
        $futureDated = $findings
            ->where('check', self::FUTURE_GATE_IN)
            ->pluck('movement.container_id')
            ->unique()
            ->flip();

        return $findings->reject(fn (array $f) => $f['check'] !== self::FUTURE_GATE_IN
            && $futureDated->has($f['movement']->container_id));
    }

    /**
     * What is wrong with one movement, or null if nothing is.
     *
     * At most one finding per movement. A gate-in dated next year is also, by
     * arithmetic, before-its-gate-out — reporting both would double the list
     * without telling anyone anything new, so the future date wins because it is
     * the one that explains the other.
     *
     * @param  array{gate_in:?GateMovement,gate_out:?GateMovement}|null  $visit
     * @return array<string,mixed>|null
     */
    private function check(GateMovement $movement, ?array $visit): ?array
    {
        $gateIn = $visit['gate_in'] ?? null;

        if ($movement->movement_type === 'in' && $movement->gate_in_time?->gt(now())) {
            return $this->finding($movement, self::FUTURE_GATE_IN,
                'Arrival dated ' . $movement->gate_in_time->format('d M Y H:i')
                . ', which is after today.');
        }

        // Reversed, judged on the visit rather than on the row: a gate-out row
        // and its gate-in row are the same problem, reported once against the
        // departure because that is the timestamp usually at fault.
        if ($movement->movement_type === 'out'
            && $gateIn?->gate_in_time
            && $movement->gate_out_time
            && $movement->gate_out_time->lt($gateIn->gate_in_time)) {
            return $this->finding($movement, self::OUT_BEFORE_IN,
                'In ' . $gateIn->gate_in_time->format('d M Y H:i')
                . ', Out ' . $movement->gate_out_time->format('d M Y H:i') . '.');
        }

        if ($movement->movement_type === 'out' && $movement->gate_out_time && ! $gateIn) {
            return $this->finding($movement, self::NO_GATE_IN,
                'Out ' . $movement->gate_out_time->format('d M Y H:i')
                . ', with no arrival on record for this container.');
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function finding(GateMovement $movement, string $check, string $detail): array
    {
        return [
            'movement'     => $movement,
            'check'        => $check,
            'label'        => self::label($check),
            'detail'       => $detail,
            'at'           => $movement->gate_in_time ?? $movement->gate_out_time,
            // Set by the controller from one query, rather than a lookup per row.
            'review'       => null,
        ];
    }

    /**
     * The movements to examine.
     *
     * Filtered on either timestamp, since a row carries only the one matching
     * its type. A future-dated arrival sits outside any range ending today, so
     * an unfiltered screen is the one that finds it — which is why the date
     * boxes start empty rather than defaulting to this month.
     *
     * @param  array{from?:?string,to?:?string,customer_id?:?int}  $filters
     * @return \Illuminate\Support\Collection<int,GateMovement>
     */
    private function movements(array $filters)
    {
        return GateMovement::query()
            ->with('customer:id,name')
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s
                ->whereDate('gate_in_time', '>=', $v)->orWhereDate('gate_out_time', '>=', $v)))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s
                ->whereDate('gate_in_time', '<=', $v)->orWhereDate('gate_out_time', '<=', $v)))
            ->orderByDesc('id')
            ->get();
    }
}
