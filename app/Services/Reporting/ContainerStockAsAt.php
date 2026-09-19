<?php

namespace App\Services\Reporting;

use App\Models\GateMovement;
use App\Services\ContainerCustodyService;
use App\Services\ContainerMrStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What was in the yard on a given date.
 *
 * The Inventory report reads the `containers` master, where every column
 * describes *now*: `status` is today's status, and `gate_in_date` /
 * `gate_out_date` / `customer_id` hold only the latest visit. A box released in
 * October therefore reads `released` and vanishes from September, and one that
 * has been in and out five times has lost the visit being asked about. That is
 * a missing dimension, not a missing filter.
 *
 * `gate_movements` is the visit ledger, and it carries the per-visit facts --
 * customer, condition, cargo status, reefer mode, size, location -- as they
 * were at the time. The rule is one sentence:
 *
 *     A container was in the yard at date D if it has a gate-in at or before D
 *     whose paired gate-out is absent or later than D.
 *
 * A point-in-time query over an event log, which is how a stock ledger or a
 * trial balance works, and it needs no snapshot table.
 */
class ContainerStockAsAt
{
    /**
     * How a row's custody reads on the report.
     *
     * The words the shipping line uses, not the yard's internal ones: from
     * their side the yard has their box "on hire", and what they most want to
     * know is when it has gone out to somebody else.
     */
    public const CUSTODY_LABELS = [
        'in_yard'    => 'In yard',
        'on_hire'    => 'On hire',
        'rented_out' => 'On hire - rented out',
    ];

    /**
     * Stock at the end of the given day.
     *
     * **End of day**, so a container that gated out at 14:00 on the as-at date
     * is *out*. That matches how the storage bill counts the gate-out day and
     * how `DateWindow` treats inclusive ranges; picking the other convention
     * would put a box on the customer's stock list and off their invoice on the
     * same day.
     *
     * @param  array{customer_id?:int|null, size?:string|null, cargo_status?:string|null,
     *               condition?:string|null, type_code?:string|null} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(string $asAt, array $filters = []): Collection
    {
        $cutoff = Carbon::parse($asAt)->endOfDay();

        // ── Pass 1: pairing ──────────────────────────────────────────────────
        //
        // This pass touches every movement of every container that had arrived
        // by the cutoff -- history, not stock -- so it has to stay cheap. Only
        // the columns the matcher reads are selected, and no relations are
        // loaded: hydrating a customer and an equipment type for every movement
        // ever recorded is work thrown away for all but the open visits.
        //
        // `whereExists` rather than a plucked id list, which on a long-running
        // yard would be tens of thousands of ids sent back into an IN clause.
        $movements = GateMovement::query()
            ->whereNotNull('container_id')
            ->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('gate_movements as arrival')
                ->whereColumn('arrival.container_id', 'gate_movements.container_id')
                ->where('arrival.movement_type', 'in')
                ->whereNotNull('arrival.gate_in_time')
                ->where('arrival.gate_in_time', '<=', $cutoff))
            ->get(['id', 'container_id', 'movement_type', 'gate_in_time', 'gate_out_time', 'yard_job_id'])
            ->groupBy('container_id');

        $pairer  = app(ContainerMrStatusService::class);
        $openIds = [];

        // Who was holding each container on that date. Read here because it
        // decides presence as well as labelling — see custodyAt().
        $custody = static::custodyAt($cutoff);

        foreach ($movements as $containerId => $perContainer) {
            $gateIns  = $perContainer->where('movement_type', 'in')
                ->filter(fn ($m) => $m->gate_in_time !== null)
                ->values();
            $gateOuts = $perContainer->where('movement_type', 'out')->values();

            if ($gateIns->isEmpty()) {
                continue;
            }

            // The yard's canonical matcher: an explicit shared job first, then a
            // time window bounded by the next gate-in. The M&R cycle, the
            // container inquiry screen and containers:fix-gate-custody all use
            // it, and a report that paired movements its own way would
            // eventually disagree with all three.
            $map = $pairer->pairGateOuts($gateIns, $gateOuts);

            $held = $custody[(int) $containerId] ?? null;

            if ($open = static::visitOpenAt($gateIns, $map, $cutoff, $held !== null)) {
                $openIds[] = $open->id;
            }
        }

        if (! $openIds) {
            return collect();
        }

        // ── Pass 2: the rows ─────────────────────────────────────────────────
        //
        // Relations are loaded only for the visits that were actually open, so
        // this pass scales with the size of the yard rather than the size of its
        // history.
        $rows = [];

        foreach (GateMovement::with(['container.equipmentType', 'customer', 'yardJob.jobType'])
            ->whereIn('id', $openIds)
            ->get() as $gateIn) {
            if ($row = static::row($gateIn, $cutoff, $filters, $custody[$gateIn->container_id] ?? null)) {
                $rows[] = $row;
            }
        }

        return collect($rows)->sortBy('container_no')->values();
    }

    /**
     * The one visit that was open at the cutoff, if any.
     *
     * Latest arrival first: a container in and out twice and back again appears
     * once, for the stay it was actually on at the time -- not once per visit.
     *
     * `$underHire` keeps a box on its owner's stock through a departure. A
     * container the yard has taken on hire and re-let physically leaves — a
     * real gate-out, correctly recorded — but the yard still owes it back, and
     * dropping it here would have the shipping line chasing containers the
     * yard is holding and paying rent on. The row that stays is the *stay's*
     * arrival, so the days count from when the box actually got here rather
     * than restarting at the rental.
     */
    private static function visitOpenAt(
        Collection $gateIns,
        array $map,
        Carbon $cutoff,
        bool $underHire = false,
    ): ?GateMovement {
        foreach ($gateIns->sortByDesc('gate_in_time') as $gateIn) {
            if ($gateIn->gate_in_time > $cutoff) {
                continue;   // had not arrived yet
            }

            $out = $map[$gateIn->id] ?? null;

            // No departure, or one after the cutoff: the box was still here.
            // A gate-out with no time cannot be placed, so it does not close
            // the visit -- Gate Data Check reports that shape separately.
            if (! $out || ! $out->gate_out_time || $out->gate_out_time > $cutoff) {
                return $gateIn;
            }

            // This visit had closed by the cutoff. The container is off stock
            // unless an agreement was still running, in which case it is off
            // the *ground* but not off the owner's books.
            return $underHire ? $gateIn : null;
        }

        return null;
    }

    /**
     * Who was holding each container on the as-at date.
     *
     * Two agreements, either of which means the box is not simply the owner's
     * to count as ordinary stock:
     *
     *   on_hire     the yard has taken it on hire from its line
     *   rented_out  and has put it out with a renting customer
     *
     * Selected on **dates, not status**. An as-at report has to answer for the
     * date asked about, and a lease that has since been off-hired was still
     * running then; reading `status = 'active'` would quietly rewrite last
     * month's stock every time an agreement closed. Cancelled agreements are
     * excluded because they never ran at all.
     *
     * `off_hire_date > asAt` rather than `>=`: a box returned on the as-at date
     * is back on the ground by the end of that day, which is the same end-of-day
     * convention the rest of this report and the storage bill use.
     *
     * @return array<int,string> container_id => custody label
     */
    private static function custodyAt(Carbon $cutoff): array
    {
        $asAt = $cutoff->toDateString();

        $running = fn ($q) => $q
            ->where('status', '!=', 'cancelled')
            ->whereDate('on_hire_date', '<=', $asAt)
            ->where(fn ($w) => $w->whereNull('off_hire_date')
                                 ->orWhereDate('off_hire_date', '>', $asAt));

        $custody = [];

        foreach (\App\Models\LessorOnHire::where($running)->pluck('container_id') as $id) {
            $custody[(int) $id] = 'on_hire';
        }

        // Applied second: a box both leased in and re-let is both, and "on
        // hire" alone would not tell the line their container has left the
        // yard. A letting with no lease behind it lands here too — the yard
        // letting out a box of its own.
        foreach (\App\Models\ContainerHire::where($running)->pluck('container_id') as $id) {
            $custody[(int) $id] = 'rented_out';
        }

        return $custody;
    }

    /** @return array<string,mixed>|null null when a filter excludes it */
    private static function row(
        GateMovement $gateIn,
        Carbon $cutoff,
        array $filters,
        ?string $custody = null,
    ): ?array {
        $container = $gateIn->container;

        // Per-visit facts come from the movement, not the master: a box that
        // arrived laden still reads laden even if the master was edited later.
        $size        = $gateIn->size        ?: $container?->size;
        $typeCode    = $gateIn->container_type ?: $container?->type_code;
        $cargoStatus = $gateIn->cargo_status ?: $container?->cargo_status;
        $condition   = $gateIn->condition   ?: $container?->condition;

        $customerId = ContainerCustodyService::resolveCustomerId(
            $gateIn->yardJob?->customer_id,
            $gateIn->customer_id,
            // Deliberately not the container master: the customer asking is the
            // shipping line who *had* boxes here, which is not necessarily who
            // the master says owns them today.
            null,
        );

        if (! static::passes($filters, $customerId, $size, $typeCode, $cargoStatus, $condition)) {
            return null;
        }

        return [
            'container_id'   => $gateIn->container_id,
            'container_no'   => $gateIn->container_no ?: $container?->container_no,
            'size'           => $size,
            'type_code'      => $typeCode,
            'equipment_type' => $container?->equipmentType?->type_code,
            'cargo_status'   => $cargoStatus,
            'reefer_mode'    => $gateIn->reefer_mode,
            'condition'      => $condition,
            'customer_id'    => $customerId,
            'customer'       => $gateIn->yardJob?->customer?->name ?? $gateIn->customer?->name,
            'gate_in_time'   => $gateIn->gate_in_time,
            // To the as-at date, never to now(). This is what makes it a stock
            // report rather than a list, and the easiest thing to get wrong.
            'days_in_yard'   => (int) $gateIn->gate_in_time->copy()->startOfDay()
                ->diffInDays($cutoff->copy()->startOfDay()),
            // A slot the box is not standing in is worse than no slot: the
            // yard releases the location at gate-out, so the arrival's snapshot
            // would send someone to look for a container that is with a renter.
            'location'       => $custody === 'rented_out' ? null : static::location($gateIn),
            'job_no'         => $gateIn->yardJob?->job_no,
            'job_type'       => $gateIn->yardJob?->jobType?->name,
            'stage'          => $container?->status,
            'movement_id'    => $gateIn->id,

            // Two different questions, answered separately, because during a
            // hire they have different answers: `custody` is whose the box is
            // commercially, `on_ground` is whether it is physically here. A
            // rented-out container is on the line's stock and off the yard.
            'custody'        => $custody ?? 'in_yard',
            'custody_label'  => static::CUSTODY_LABELS[$custody ?? 'in_yard'],
            'on_ground'      => $custody !== 'rented_out',
        ];
    }

    private static function passes(
        array $filters,
        ?int $customerId,
        ?string $size,
        ?string $typeCode,
        ?string $cargoStatus,
        ?string $condition,
    ): bool {
        $wants = fn (string $key, mixed $actual) => empty($filters[$key])
            || (string) $filters[$key] === (string) $actual;

        return $wants('customer_id', $customerId)
            && $wants('size', $size)
            && $wants('type_code', $typeCode)
            && $wants('cargo_status', $cargoStatus)
            && $wants('condition', $condition);
    }

    private static function location(GateMovement $m): ?string
    {
        if (! $m->location_row) {
            return null;
        }

        return trim($m->location_row . '-' . $m->location_bay . '-' . $m->location_tier, '-');
    }

    /**
     * Containers with movements but no usable gate-in.
     *
     * They cannot be placed in time, so they cannot appear in the rows -- and a
     * customer's count that is silently short is what starts a dispute. Gate
     * Data Check's NO_GATE_IN reports the same shape for correction.
     */
    public static function unplaceableCount(): int
    {
        return GateMovement::query()
            ->whereNotNull('container_id')
            ->whereNotIn('container_id', GateMovement::query()
                ->where('movement_type', 'in')
                ->whereNotNull('gate_in_time')
                ->whereNotNull('container_id')
                ->select('container_id'))
            ->distinct('container_id')
            ->count('container_id');
    }

    /** Totals for the tiles: the second question every time is "how many 40s?". */
    public static function summary(Collection $rows): array
    {
        return [
            'total'  => $rows->count(),
            'laden'  => $rows->where('cargo_status', 'laden')->count(),
            'empty'  => $rows->where('cargo_status', 'empty')->count(),
            // Of the total, how many are the yard's responsibility but not on
            // its ground. Stated rather than left to be inferred: a count that
            // silently mixes the two is what starts a dispute.
            'on_hire'    => $rows->where('custody', 'on_hire')->count(),
            'rented_out' => $rows->where('custody', 'rented_out')->count(),
            'on_ground'  => $rows->where('on_ground', true)->count(),
            'by_size' => $rows->groupBy('size')
                ->map->count()
                ->sortKeys()
                ->all(),
            'teu'    => $rows->reduce(
                fn ($carry, $r) => $carry + ((string) $r['size'] === '20' ? 1 : 2),
                0,
            ),
        ];
    }
}
