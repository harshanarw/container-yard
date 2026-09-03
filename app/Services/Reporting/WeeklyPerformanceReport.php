<?php

namespace App\Services\Reporting;

use App\Models\Customer;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Weekly performance — a per-customer count of yard lifts across a date range,
 * cut into weeks and split by size and by laden/empty.
 *
 * Two rows per customer:
 *
 *  - **Demounting** — Lift Off, the box coming off the truck into the yard:
 *    `movement_type = 'in'`, dated by `gate_in_time`.
 *  - **Mounting** — Lift On, the box going onto the truck:
 *    `movement_type = 'out'`, dated by `gate_out_time`.
 *
 * That mapping is the one the billing code already uses
 * (`StorageHandlingController::preview`), and reusing it is the point: this
 * report and the handling lines on a Storage & Handling invoice count the same
 * events by the same rule, so they reconcile. If they ever disagree, one of them
 * is wrong — rather than both being defensible.
 *
 * **Every counted attribute comes off the movement row, never off the container.**
 * A box that arrives laden and leaves empty is counted laden on its Demounting
 * row and empty on its Mounting row, which is what the yard actually did.
 * Reading `containers.size` or `containers.cargo_status` would report the state
 * the box is in today and quietly misstate every past week.
 */
class WeeklyPerformanceReport
{
    /** Column order within each half of a week band. */
    public const SIZES = ['20', '40', '45'];

    /** Half-band order: empty first, then laden, as the sample has it. */
    public const STATUSES = ['empty', 'laden'];

    public const DEMOUNTING = 'demounting';
    public const MOUNTING   = 'mounting';

    /**
     * @param  array{week_rule?:string,customer_id?:int|null,only_with_movements?:bool}  $options
     * @return array<string,mixed>
     */
    public function build(string $from, string $to, array $options = []): array
    {
        $rule = $options['week_rule'] ?? WeekBreakdown::DEFAULT;
        if (! WeekBreakdown::isRule($rule)) {
            $rule = WeekBreakdown::DEFAULT;
        }

        $weeks      = WeekBreakdown::for($from, $to, $rule);
        $customerId = $options['customer_id'] ?? null;

        $counts = [
            self::DEMOUNTING => $this->tally('in', 'gate_in_time', $from, $to, $customerId),
            self::MOUNTING   => $this->tally('out', 'gate_out_time', $from, $to, $customerId),
        ];

        // Anchors the arithmetic. Every lift the range contains must end up in
        // exactly one cell; comparing the grand total against this raw count is
        // what catches a lift that fell into no week at all — a bug every
        // subtotal would otherwise hide, because they would still agree with
        // each other.
        $movementCount = 0;
        foreach ($counts as $tally) {
            foreach ($tally as $row) {
                $movementCount += (int) $row->n;
            }
        }

        $moved = $this->customerIdsWithMovements($counts);
        $rows  = [];

        foreach ($this->customers($customerId, $moved, (bool) ($options['only_with_movements'] ?? false)) as $customer) {
            $row = [
                'customer_id' => $customer->id,
                'customer'    => $customer->name,
                'code'        => $customer->code,
                'moved'       => in_array($customer->id, $moved, true),
            ];

            foreach ([self::DEMOUNTING, self::MOUNTING] as $direction) {
                $row[$direction] = $this->pivot($counts[$direction], $customer->id, $weeks);
            }

            $rows[] = $row;
        }

        return [
            'from'           => $from,
            'to'             => $to,
            'week_rule'      => $rule,
            'weeks'          => $weeks,
            'sizes'          => self::SIZES,
            'statuses'       => self::STATUSES,
            'columns'        => self::columns(),
            'rows'           => $rows,
            'totals'         => $this->totals($rows, $weeks),
            'title'          => self::title($from, $to),
            'movement_count' => $movementCount,
            // Movements whose size or cargo status is outside the known set, and
            // so land in no column. Should always be zero — both are database
            // enums — but a silent drop is worse than a visible one, and the
            // grand-total invariant would break without somewhere to account
            // for them.
            'unmapped'       => $this->unmapped($counts),
        ];
    }

    /** The twelve cell keys of a band, in column order: `empty_20` … `laden_45`. */
    public static function columns(): array
    {
        $keys = [];
        foreach (self::STATUSES as $status) {
            foreach (self::SIZES as $size) {
                $keys[] = $status . '_' . $size;
            }
        }

        return $keys;
    }

    /** A band with every cell at zero. */
    public static function emptyCells(): array
    {
        return array_fill_keys(self::columns(), 0);
    }

    /**
     * One grouped query per direction.
     *
     * Filtering on the raw timestamp rather than `DATE(gate_in_time)` keeps the
     * range scan on the index; wrapping the column in a function would defeat
     * it. The bound is half-open — `>= from 00:00:00` and `< to+1 day` — so a
     * lift at 23:59:58 on the last day is included, which a `BETWEEN` against
     * two dates would silently drop.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function tally(string $type, string $column, string $from, string $to, ?int $customerId)
    {
        $end = (new DateTimeImmutable(substr($to, 0, 10)))->modify('+1 day')->format('Y-m-d');

        return DB::table('gate_movements')
            ->selectRaw("customer_id, size, cargo_status, DATE({$column}) as d, COUNT(*) as n")
            ->where('movement_type', $type)
            ->whereNotNull($column)
            ->where($column, '>=', substr($from, 0, 10) . ' 00:00:00')
            ->where($column, '<', $end . ' 00:00:00')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->groupBy('customer_id', 'size', 'cargo_status', DB::raw("DATE({$column})"))
            ->get();
    }

    /**
     * Turns one customer's grouped counts into per-week bands plus a row total.
     *
     * @return array{weeks:array<int,array<string,int>>,total:array<string,int>}
     */
    private function pivot($tally, int $customerId, array $weeks): array
    {
        $bands = array_map(fn () => self::emptyCells(), $weeks);
        $total = self::emptyCells();

        foreach ($tally as $row) {
            if ((int) $row->customer_id !== $customerId) {
                continue;
            }

            $key = $row->cargo_status . '_' . $row->size;
            if (! array_key_exists($key, $total)) {
                continue;   // counted under 'unmapped' instead
            }

            $index = WeekBreakdown::indexFor($weeks, (string) $row->d);
            if ($index === null) {
                continue;
            }

            $bands[$index][$key] += (int) $row->n;
            $total[$key]        += (int) $row->n;
        }

        return ['weeks' => $bands, 'total' => $total];
    }

    /**
     * The three footer rows: Demounting, Mounting, then the two added together.
     *
     * Demounting leads, matching the order of the pair under every customer
     * above — a footer that reversed them would invite reading the wrong line.
     *
     * The grand total is total lifts, mixing both directions. That is the
     * quantity the sample's single `TOTAL` row already held (checked against its
     * own arithmetic), and it is the one the yard is really after: how much the
     * cranes did.
     */
    private function totals(array $rows, array $weeks): array
    {
        $blank = fn () => ['weeks' => array_map(fn () => self::emptyCells(), $weeks), 'total' => self::emptyCells()];

        $out = [
            self::DEMOUNTING => $blank(),
            self::MOUNTING   => $blank(),
            'grand'          => $blank(),
        ];

        foreach ($rows as $row) {
            foreach ([self::DEMOUNTING, self::MOUNTING] as $direction) {
                foreach ($row[$direction]['weeks'] as $i => $cells) {
                    foreach ($cells as $key => $n) {
                        $out[$direction]['weeks'][$i][$key] += $n;
                        $out['grand']['weeks'][$i][$key]    += $n;
                    }
                }
                foreach ($row[$direction]['total'] as $key => $n) {
                    $out[$direction]['total'][$key] += $n;
                    $out['grand']['total'][$key]    += $n;
                }
            }
        }

        return $out;
    }

    /**
     * Active customers, plus any customer with movements in the range even if
     * they are no longer active.
     *
     * An inactive customer who moved boxes in March still moved them, and
     * dropping their rows would leave the column totals disagreeing with the
     * yard's actual lift count for no reason the reader could see.
     *
     * @param  array<int,int>  $moved
     * @return \Illuminate\Support\Collection<int,Customer>
     */
    private function customers(?int $customerId, array $moved, bool $onlyWithMovements)
    {
        $query = Customer::query()->orderBy('name');

        // Asked for by name, shown by name. Someone who picks a customer from
        // the filter has already decided they want that customer, and hiding
        // them for being inactive or quiet would answer a question nobody asked.
        if ($customerId) {
            return $query->where('id', $customerId)->get(['id', 'code', 'name']);
        }

        return $query
            ->when(
                $onlyWithMovements,
                fn ($q) => $q->whereIn('id', $moved ?: [0]),
                fn ($q) => $q->where(fn ($s) => $s->where('status', 'active')->orWhereIn('id', $moved ?: [0])),
            )
            ->get(['id', 'code', 'name']);
    }

    /** @return array<int,int> */
    private function customerIdsWithMovements(array $counts): array
    {
        $ids = [];
        foreach ($counts as $tally) {
            foreach ($tally as $row) {
                $ids[(int) $row->customer_id] = true;
            }
        }

        return array_keys($ids);
    }

    private function unmapped(array $counts): int
    {
        $known = self::emptyCells();
        $n     = 0;

        foreach ($counts as $tally) {
            foreach ($tally as $row) {
                if (! array_key_exists($row->cargo_status . '_' . $row->size, $known)) {
                    $n += (int) $row->n;
                }
            }
        }

        return $n;
    }

    /**
     * "PERFORMANCE UPDATE [NO. OF UNITS] — AUGUST 2026" where the range is
     * exactly one calendar month, as the sample has it, and the two dates
     * otherwise. A month named is easier to file than a pair of dates that
     * happen to be its ends.
     */
    public static function title(string $from, string $to): string
    {
        $start = new DateTimeImmutable(substr($from, 0, 10));
        $end   = new DateTimeImmutable(substr($to, 0, 10));

        $wholeMonth = $start->format('d') === '01'
            && $start->format('Y-m') === $end->format('Y-m')
            && $end->format('d') === $start->format('t');

        $period = $wholeMonth
            ? strtoupper($start->format('F Y'))
            : strtoupper($start->format('d M Y') . ' to ' . $end->format('d M Y'));

        return 'PERFORMANCE UPDATE [NO. OF UNITS] — ' . $period;
    }
}
