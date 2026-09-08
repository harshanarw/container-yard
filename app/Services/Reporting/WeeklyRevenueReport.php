<?php

namespace App\Services\Reporting;

use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\HandlingTariff;
use App\Models\StorageMasterDetail;
use App\Models\StorageMasterHeader;
use App\Models\YardStorage;
use App\Services\Billing\ManualPricing;
use App\Services\CurrencyService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Weekly performance — revenue. The money twin of {@see WeeklyPerformanceReport},
 * cut into the same weeks by the same rule so the two can be read side by side.
 *
 * Seven lines per customer, on two different bases, and the difference matters:
 *
 *  - **De-mounting, Mounting, Storage** are *earned*, computed from gate
 *    movements and the tariffs in force. They do not wait for invoicing, which
 *    runs monthly — a weekly view built from invoices would show three empty
 *    weeks and a spike.
 *  - **Electricity, PTI, Overtime, Other** are *billed*, read from issued
 *    documents dated in the week.
 *
 * The consequence is that this report **will not tie to the invoice ledger or
 * the GL**, and the screen says so. That is the honest cost of answering "what
 * did the yard earn this week" rather than "what did we invoice".
 *
 * De-mounting and Mounting use the mapping the count report and
 * `StorageHandlingController::preview` already use — Lift Off is a gate-in,
 * Lift On is a gate-out. Three places now agree, so this report's De-mounting
 * count must equal the count report's for the same week and customer.
 */
class WeeklyRevenueReport
{
    public const DEMOUNTING  = 'demounting';
    public const MOUNTING    = 'mounting';
    public const STORAGE     = 'storage';
    public const ELECTRICITY = 'electricity';
    public const PTI         = 'pti';
    public const OVERTIME    = 'overtime';
    public const OTHER       = 'other';

    /** Row order within every customer block, and in the category footer. */
    public const CATEGORIES = [
        self::DEMOUNTING,
        self::MOUNTING,
        self::STORAGE,
        self::ELECTRICITY,
        self::PTI,
        self::OVERTIME,
        self::OTHER,
    ];

    /**
     * Invoice statuses that represent revenue.
     *
     * Everything except `draft`, `cancelled` and `void` — expressed as an
     * exclusion because the four AR tables do not share one status vocabulary
     * (`ot_receipts` alone adds `generated`, `partially_used` and `fully_used`,
     * and defaults to `generated` rather than `draft`). Listing what does *not*
     * count is the only form that stays correct when a status is added.
     */
    public const EXCLUDED_STATUSES = ['draft', 'cancelled', 'void'];

    /**
     * General-invoice categories that belong on the Overtime row rather than on
     * Other.
     *
     * Nothing links `general_invoices` to `ot_receipts` — the OT module never
     * raises a General Invoice — so a general invoice of this category is a
     * separate charge for a different event, not a restatement of a receipt.
     * Excluding it would lose the revenue; leaving it in Other would split
     * overtime across two rows. It joins the row whose label it answers to.
     */
    public const OVERTIME_CATEGORIES = ['overtime'];

    public static function labels(): array
    {
        return [
            self::DEMOUNTING  => 'De-mounting',
            self::MOUNTING    => 'Mounting',
            self::STORAGE     => 'Storage',
            self::ELECTRICITY => 'Electricity',
            self::PTI         => 'PTI',
            self::OVERTIME    => 'Overtime',
            self::OTHER       => 'Other (Repair, Washing, etc.)',
        ];
    }

    /** @var array<int,string> collected while building; shown above the grid */
    private array $issues = [];

    /**
     * The same problems, attributed to the cell that has them:
     * `[customerId][category] => [message, …]`.
     *
     * A cell left blank because no tariff is configured and a cell left blank
     * because nothing happened are the same blank, and the difference is the
     * difference between missing configuration and a quiet week. The banner
     * says something is wrong; this says *where*.
     *
     * @var array<int,array<string,array<int,string>>>
     */
    private array $cellIssues = [];

    /**
     * @param  array{week_rule?:string,customer_id?:int,only_with_revenue?:bool}  $options
     */
    public function build(string $from, string $to, array $options = []): array
    {
        $this->issues     = [];
        $this->cellIssues = [];

        $rule = $options['week_rule'] ?? WeekBreakdown::DEFAULT;
        if (! WeekBreakdown::isRule($rule)) {
            $rule = WeekBreakdown::DEFAULT;
        }

        $from  = substr($from, 0, 10);
        $to    = substr($to, 0, 10);
        $weeks = WeekBreakdown::for($from, $to, $rule);
        $only  = $options['customer_id'] ?? null;

        // cells[customerId][category][weekIndex] = amount
        $cells = [];

        if ($weeks) {
            $this->addHandling($cells, $weeks, $from, $to, $only);
            $this->addStorage($cells, $weeks, $from, $to, $only);
            $this->addBilled($cells, $weeks, $only);
        }

        $rows = [];
        foreach ($this->customers($only, array_keys($cells), (bool) ($options['only_with_revenue'] ?? false)) as $customer) {
            $rows[] = self::row(
                $customer->id, $customer->name, $customer->code,
                $cells[$customer->id] ?? [], $weeks, $this->cellIssues[$customer->id] ?? [],
            );
        }

        return [
            'from'            => $from,
            'to'              => $to,
            'week_rule'       => $rule,
            'weeks'           => $weeks,
            'categories'      => self::CATEGORIES,
            'labels'          => self::labels(),
            'currency'        => CurrencyService::defaultCurrency(),
            'rows'            => $rows,
            // Generated, labelled, and inside the grand total at zero. Nothing
            // in the system models rental income yet; the row holds its place so
            // the sheet keeps the shape the yard knows and nothing moves when a
            // source appears.
            'rent'            => ['weeks' => array_fill(0, count($weeks), 0.0), 'total' => 0.0],
            'category_totals' => self::categoryTotals($rows, $weeks),
            'grand'           => self::grand($rows, $weeks),
            'title'           => self::title($from, $to),
            'issues'          => array_values(array_unique($this->issues)),
        ];
    }

    /**
     * "PERFORMANCE UPDATE [REVENUE] — AUGUST 2026", collapsing to the month name
     * when the range is exactly one month. Mirrors the count report's title so
     * the pair reads as a pair.
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

        return 'PERFORMANCE UPDATE [REVENUE] — ' . $period;
    }

    // ── Earned: handling ────────────────────────────────────────────────────

    /**
     * Lifts × the handling rate for that size and cargo status.
     *
     * Grouped in SQL by customer, size, cargo status and date, then priced in
     * PHP — because the rate depends on which tariff was in force on the day,
     * which SQL would need a join per week to express.
     */
    private function addHandling(array &$cells, array $weeks, string $from, string $to, ?int $only): void
    {
        $directions = [
            self::DEMOUNTING => ['in',  'gate_in_time'],
            self::MOUNTING   => ['out', 'gate_out_time'],
        ];

        $end = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');

        foreach ($directions as $category => [$type, $column]) {
            $tally = DB::table('gate_movements')
                ->selectRaw("customer_id, size, cargo_status, DATE({$column}) as d, COUNT(*) as n")
                ->where('movement_type', $type)
                ->whereNotNull($column)
                ->whereNotNull('customer_id')
                ->where($column, '>=', $from . ' 00:00:00')
                ->where($column, '<', $end . ' 00:00:00')
                ->when($only, fn ($q) => $q->where('customer_id', $only))
                ->groupBy('customer_id', 'size', 'cargo_status', DB::raw("DATE({$column})"))
                ->get();

            $tariffs = $this->handlingTariffs($tally->pluck('customer_id')->unique()->all());

            foreach ($tally as $r) {
                $week = WeekBreakdown::indexFor($weeks, $r->d);
                if ($week === null) {
                    continue;
                }

                $rate = $this->handlingRate(
                    $tariffs[(int) $r->customer_id] ?? [],
                    (int) $r->customer_id,
                    self::normalizeSize((string) $r->size),
                    (string) $r->cargo_status,
                    $r->d,
                    $category,
                );

                if ($rate !== null) {
                    $cells[(int) $r->customer_id][$category][$week] =
                        ($cells[(int) $r->customer_id][$category][$week] ?? 0.0) + $rate * (int) $r->n;
                }
            }
        }
    }

    /** The lift-off or lift-on rate, in base currency, or null when unpriceable. */
    private function handlingRate(array $tariffs, int $customerId, string $size, string $cargo, string $date, string $category): ?float
    {
        if ($size === '') {
            $this->issue("A gate movement carries a container size outside 20/40/45 and cannot be priced.", $customerId, $category);

            return null;
        }

        $tariff = $this->validAt($tariffs, $date);
        if (! $tariff) {
            $this->issue("No handling tariff in force on {$date}.", $customerId, $category);

            return null;
        }

        $rate = $tariff->rates
            ->where('container_size', $size)
            ->where('cargo_status', $cargo)
            ->first();

        if (! $rate) {
            $this->issue("Handling tariff #{$tariff->id} has no {$size}' {$cargo} rate.", $customerId, $category);

            return null;
        }

        $column = $category === self::DEMOUNTING ? 'lift_off_rate' : 'lift_on_rate';
        $mult   = $this->tariffMultiplier((string) ($rate->currency ?? 'USD'), $date, $customerId, $category);

        return $mult === null ? null : (float) $rate->{$column} * $mult;
    }

    // ── Earned: storage ─────────────────────────────────────────────────────

    /**
     * Storage accrues per day across a stay that spans weeks, so it is the one
     * line that has to be sliced rather than dated.
     *
     * The free-day allocation is not re-derived here. `ManualPricing` already
     * consumes the allowance from a "days elapsed before this window" argument,
     * so calling it once per week — each with a larger `daysBefore` — spends the
     * allowance chronologically across the weeks for free. Re-granting free days
     * per week would understate revenue by roughly a week per container, every
     * time, with nothing on screen to show for it.
     */
    private function addStorage(array &$cells, array $weeks, string $from, string $to, ?int $only): void
    {
        $stays = YardStorage::with('container.equipmentType')
            ->whereNotNull('customer_id')
            ->whereDate('gate_in_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('gate_out_date')->orWhereDate('gate_out_date', '>=', $from))
            ->when($only, fn ($q) => $q->where('customer_id', $only))
            ->get();

        if ($stays->isEmpty()) {
            return;
        }

        $headers = $this->storageHeaders($stays->pluck('customer_id')->unique()->all());
        $visit   = $this->visitFacts($stays->pluck('container_id')->filter()->unique()->all());
        $today   = date('Y-m-d');

        foreach ($stays as $stay) {
            $container = $stay->container;
            $eqtId     = $container?->equipment_type_id;
            $facts     = $visit[$stay->container_id] ?? null;
            $cargo     = $facts->cargo_status ?? $container?->cargo_status ?? 'empty';
            $mode      = $facts->reefer_mode ?? null;

            $anchor = $stay->billing_gate_in_date->format('Y-m-d');
            $inDate = $stay->gate_in_date->format('Y-m-d');
            // A stay still in the yard accrues to today, never to the end of a
            // range someone typed into the future.
            $outDate = $stay->gate_out_date?->format('Y-m-d');

            $freeDays = (int) ($this->validAt($headers[(int) $stay->customer_id] ?? [], $from)?->default_free_days
                               ?? $stay->free_days ?? 0);

            foreach (self::chargeableDaysByWeek($weeks, $inDate, $outDate, $anchor, $freeDays, $today) as $i => $chargeable) {
                $week = $weeks[$i];
                $rate = $this->storageRate($headers, (int) $stay->customer_id, $eqtId, $cargo, $mode, $week['from']);

                if ($rate !== null) {
                    $cells[(int) $stay->customer_id][self::STORAGE][$i] =
                        ($cells[(int) $stay->customer_id][self::STORAGE][$i] ?? 0.0) + $rate * $chargeable;
                }
            }
        }
    }

    /**
     * How many chargeable days of one stay fall in each week — the whole of the
     * storage split, as pure arithmetic, so it can be checked without a
     * database.
     *
     * Weeks with nothing to charge are absent from the result rather than
     * present as zero, so the caller never resolves a tariff for a week that
     * would price nothing.
     *
     * @param  array<int,array{from:string,to:string}>  $weeks
     * @param  string       $gateIn   first billable day of the stay
     * @param  string|null  $gateOut  null while the box is still in the yard
     * @param  string       $anchor   free-day origin — `billing_gate_in_date`,
     *                                which survives a hire so free time is not
     *                                granted twice
     * @return array<int,int> week index => chargeable days
     */
    public static function chargeableDaysByWeek(
        array $weeks,
        string $gateIn,
        ?string $gateOut,
        string $anchor,
        int $freeDays,
        string $today,
    ): array {
        $out = [];

        foreach ($weeks as $i => $week) {
            $start = max($gateIn, $week['from']);
            // A stay still in the yard accrues to today, never to the end of a
            // range someone typed into the future.
            $stop  = min($gateOut ?? min($week['to'], $today), $week['to']);

            // Covers three cases at once: a week before the box arrived, a week
            // after it left, and a backwards pair whose gate-out precedes its
            // gate-in. None of them is negative storage.
            if ($stop < $start) {
                continue;
            }

            $daysInWeek = self::daysBetween($start, $stop) + 1;   // inclusive; same-day is 1
            $daysBefore = max(0, self::daysBetween($anchor, $start));

            // The free allowance is spent chronologically across the weeks
            // without any bookkeeping here, because each later week passes a
            // larger `daysBefore` and `ManualPricing` measures what is left from
            // that alone.
            $chargeable = ManualPricing::chargeableDays($freeDays, $daysBefore, $daysInWeek);

            if ($chargeable > 0) {
                $out[$i] = $chargeable;
            }
        }

        return $out;
    }

    /** The daily storage rate, in base currency, or null when unpriceable. */
    private function storageRate(array $headers, int $customerId, ?int $eqtId, string $cargo, ?string $mode, string $date): ?float
    {
        $header = $this->validAt($headers[$customerId] ?? [], $date);

        if (! $header) {
            $this->issue("No storage tariff in force on {$date}.", $customerId, self::STORAGE);

            return null;
        }

        $detail = StorageMasterDetail::resolve($header->details, $eqtId, $cargo, $mode);

        if (! $detail) {
            $this->issue("Storage tariff #{$header->id} has no rate for equipment type #{$eqtId} ({$cargo}).", $customerId, self::STORAGE);

            return null;
        }

        $mult = $this->tariffMultiplier((string) ($detail->currency ?? 'USD'), $date, $customerId, self::STORAGE);

        return $mult === null ? null : (float) $detail->storage_rate * $mult;
    }

    // ── Billed: electricity, PTI, overtime, other ───────────────────────────

    /**
     * The four invoice-driven lines.
     *
     * Every source is filtered on its own date column and summed in base
     * currency. `total_value` is already base on the tables that carry it; the
     * rest convert through their stored `exchange_rate` — never today's rate,
     * which would make last month's report change value overnight.
     */
    private function addBilled(array &$cells, array $weeks, ?int $only): void
    {
        $from = $weeks[0]['from'];
        $to   = $weeks[array_key_last($weeks)]['to'];

        // Electricity and PTI: one table, split on the service_type the reefer
        // module already records, so this is a GROUP BY rather than a heuristic.
        $reefer = DB::table('reefer_electricity_invoices')
            ->selectRaw('customer_id, service_type, invoice_date as d, SUM(total_value) as amt')
            ->whereNotNull('customer_id')
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereBetween('invoice_date', [$from, $to])
            ->when($only, fn ($q) => $q->where('customer_id', $only))
            ->groupBy('customer_id', 'service_type', 'invoice_date')
            ->get();

        foreach ($reefer as $r) {
            $this->put($cells, $weeks, (int) $r->customer_id,
                $r->service_type === 'pti' ? self::PTI : self::ELECTRICITY,
                (string) $r->d, (float) $r->amt);
        }

        // Overtime receipts, dated by the day the overtime relates to. An
        // extension copies its parent's operational_date, so it lands in the
        // same week rather than drifting into the next; and an extension is a
        // full new charge, not a restatement, so both count.
        $ot = DB::table('ot_receipts')
            ->selectRaw('customer_id, operational_date as d, SUM(total_amount) as amt')
            ->whereNotNull('customer_id')
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereBetween('operational_date', [$from, $to])
            ->when($only, fn ($q) => $q->where('customer_id', $only))
            ->groupBy('customer_id', 'operational_date')
            ->get();

        foreach ($ot as $r) {
            $this->put($cells, $weeks, (int) $r->customer_id, self::OVERTIME, (string) $r->d, (float) $r->amt);
        }

        // Repair, washing and M&R.
        $repair = DB::table('repair_invoices')
            ->selectRaw('customer_id, invoice_date as d, SUM(grand_total * COALESCE(exchange_rate, 1)) as amt')
            ->whereNotNull('customer_id')
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereBetween('invoice_date', [$from, $to])
            ->when($only, fn ($q) => $q->where('customer_id', $only))
            ->groupBy('customer_id', 'invoice_date')
            ->get();

        foreach ($repair as $r) {
            $this->put($cells, $weeks, (int) $r->customer_id, self::OTHER, (string) $r->d, (float) $r->amt);
        }

        // Everything else invoiced. Storage & handling invoices are never read —
        // rows 1-3 compute that revenue from the movements themselves, and
        // reading both would count it twice.
        $general = DB::table('general_invoices')
            ->selectRaw('customer_id, category, invoice_date as d, SUM(grand_total * COALESCE(exchange_rate, 1)) as amt')
            ->whereNotNull('customer_id')
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->whereBetween('invoice_date', [$from, $to])
            ->when($only, fn ($q) => $q->where('customer_id', $only))
            ->groupBy('customer_id', 'category', 'invoice_date')
            ->get();

        foreach ($general as $r) {
            $category = in_array($r->category, self::OVERTIME_CATEGORIES, true) ? self::OVERTIME : self::OTHER;
            $this->put($cells, $weeks, (int) $r->customer_id, $category, (string) $r->d, (float) $r->amt);
        }
    }

    private function put(array &$cells, array $weeks, int $customerId, string $category, string $date, float $amount): void
    {
        $week = WeekBreakdown::indexFor($weeks, $date);

        if ($week !== null) {
            $cells[$customerId][$category][$week] = ($cells[$customerId][$category][$week] ?? 0.0) + $amount;
        }
    }

    // ── Assembly ────────────────────────────────────────────────────────────

    /**
     * One customer block: seven category rows and the Total that must equal
     * their sum. Scalars rather than a model so the rollup can be checked
     * without a database.
     *
     * `$issues` is this customer's slice of `cellIssues`, so a category that
     * could not be priced carries the reason rather than an unexplained blank.
     */
    public static function row(int $id, string $name, ?string $code, array $found, array $weeks, array $issues = []): array
    {
        $n          = count($weeks);
        $categories = [];
        $weekTotals = array_fill(0, $n, 0.0);
        $grand      = 0.0;

        foreach (self::CATEGORIES as $category) {
            $cells = [];
            $total = 0.0;

            for ($i = 0; $i < $n; $i++) {
                $amount   = round($found[$category][$i] ?? 0.0, 2);
                $cells[]  = $amount;
                $total   += $amount;
                $weekTotals[$i] += $amount;
            }

            $categories[$category] = [
                'weeks' => $cells,
                'total' => round($total, 2),
                // What the cell's marker says on hover. Null when the line is
                // simply quiet, which is the distinction the marker exists for.
                'issue' => isset($issues[$category])
                    ? implode(' ', array_keys($issues[$category]))
                    : null,
            ];
            $grand += $total;
        }

        return [
            'customer_id' => $id,
            'customer'    => $name,
            'code'        => $code,
            'categories'  => $categories,
            // The eighth row of the block. Its whole job is to be checkable:
            // it must equal the sum of the seven above it.
            'total'       => [
                'weeks' => array_map(fn ($v) => round($v, 2), $weekTotals),
                'total' => round($grand, 2),
                // A total built over an unpriced category is itself short. The
                // Total row is the one a reader trusts, so it carries the flag.
                // array_values first: $issues is keyed by category, and spreading
                // a string-keyed array is a named-argument call in PHP 8.
                'issue' => $issues
                    ? 'This total is understated. ' . implode(' ', array_unique(
                        array_merge(...array_values(array_map('array_keys', $issues)))
                    ))
                    : null,
            ],
            'earned'      => $grand > 0,
        ];
    }

    /**
     * One row per category, summed across every customer.
     *
     * This block is the report's second, independent path to the grand total:
     * these seven must sum to the same figure as the customer totals plus rent.
     * When they disagree the report has a bug, and that is worth finding from a
     * test rather than from a reader.
     */
    public static function categoryTotals(array $rows, array $weeks): array
    {
        $out = [];

        foreach (self::CATEGORIES as $category) {
            $cells = array_fill(0, count($weeks), 0.0);
            $total = 0.0;

            foreach ($rows as $row) {
                foreach ($row['categories'][$category]['weeks'] as $i => $amount) {
                    $cells[$i] += $amount;
                }
                $total += $row['categories'][$category]['total'];
            }

            $out[$category] = ['weeks' => array_map(fn ($v) => round($v, 2), $cells), 'total' => round($total, 2)];
        }

        return $out;
    }

    /** Customer totals plus rent — never every row, which would double count. */
    public static function grand(array $rows, array $weeks): array
    {
        $cells = array_fill(0, count($weeks), 0.0);
        $total = 0.0;

        foreach ($rows as $row) {
            foreach ($row['total']['weeks'] as $i => $amount) {
                $cells[$i] += $amount;
            }
            $total += $row['total']['total'];
        }

        return ['weeks' => array_map(fn ($v) => round($v, 2), $cells), 'total' => round($total, 2)];
    }

    /**
     * @param  array<int,int>  $earning
     * @return \Illuminate\Support\Collection<int,Customer>
     */
    private function customers(?int $customerId, array $earning, bool $onlyWithRevenue)
    {
        $query = Customer::query()->orderBy('name');

        // Asked for by name, shown by name — the same rule the count report
        // follows, so the two reports list the same customers.
        if ($customerId) {
            return $query->where('id', $customerId)->get(['id', 'code', 'name']);
        }

        return $query
            ->when(
                $onlyWithRevenue,
                fn ($q) => $q->whereIn('id', $earning ?: [0]),
                fn ($q) => $q->where(fn ($s) => $s->where('status', 'active')->orWhereIn('id', $earning ?: [0])),
            )
            ->get(['id', 'code', 'name']);
    }

    // ── Tariff lookup ───────────────────────────────────────────────────────

    /**
     * Handling tariffs per customer, newest first.
     *
     * All of a customer's tariffs, not just the one covering the range: a stay
     * or a range spanning a rate change should price each week at the rate then
     * in force, which the invoice — resolving one tariff per run — cannot do.
     *
     * @return array<int,array<int,HandlingTariff>>
     */
    private function handlingTariffs(array $customerIds): array
    {
        if (! $customerIds) {
            return [];
        }

        return HandlingTariff::with('rates')
            ->whereIn('shipping_line_id', $customerIds)
            ->where('is_active', true)
            ->orderByDesc('valid_from')
            ->get()
            ->groupBy('shipping_line_id')
            ->map(fn ($g) => $g->values()->all())
            ->all();
    }

    /** @return array<int,array<int,StorageMasterHeader>> */
    private function storageHeaders(array $customerIds): array
    {
        if (! $customerIds) {
            return [];
        }

        return StorageMasterHeader::with('details')
            ->whereIn('customer_id', $customerIds)
            ->where('is_active', true)
            ->orderByDesc('valid_from')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($g) => $g->values()->all())
            ->all();
    }

    /** The newest tariff whose validity window contains `$date`. */
    private function validAt(array $tariffs, string $date)
    {
        foreach ($tariffs as $tariff) {
            $fromOk = $tariff->valid_from === null || $tariff->valid_from->format('Y-m-d') <= $date;
            $toOk   = $tariff->valid_to === null   || $tariff->valid_to->format('Y-m-d')   >= $date;

            if ($fromOk && $toOk) {
                return $tariff;
            }
        }

        return null;
    }

    /**
     * Cargo status and reefer mode per container, from its most recent gate-in.
     *
     * Both come off the same movement, exactly as `StorageHandlingController`
     * does it — reading them from different places is how a box ends up priced
     * as laden on one axis and empty on the other.
     *
     * @return array<int,object>
     */
    private function visitFacts(array $containerIds): array
    {
        if (! $containerIds) {
            return [];
        }

        return GateMovement::query()
            ->select(['container_id', 'cargo_status', 'reefer_mode', 'gate_in_time'])
            ->whereIn('container_id', $containerIds)
            ->where('movement_type', 'in')
            ->orderByDesc('gate_in_time')
            ->get()
            ->keyBy('container_id')
            ->all();
    }

    /**
     * Tariff currency → base currency, at the rate in force that week.
     *
     * Null when a USD tariff meets a period with no rate on file. That is not a
     * zero: pretending an unconvertible amount is nothing would quietly shrink
     * the week. It is recorded as an issue and the cell is left out.
     */
    private function tariffMultiplier(string $currency, string $date, int $customerId, string $category): ?float
    {
        if (strtoupper($currency) === CurrencyService::defaultCurrency()) {
            return 1.0;
        }

        $rate = CurrencyService::usdToDefault($date);

        if ($rate === null) {
            $this->issue("No USD exchange rate on file for {$date}; USD tariff amounts are omitted.", $customerId, $category);

            return null;
        }

        return $rate;
    }

    // ── Small helpers ───────────────────────────────────────────────────────

    /**
     * Record a problem, and where it belongs.
     *
     * Without the customer and category it is only a banner line; with them the
     * cell can carry a marker, which is what turns "something is unpriced" into
     * "this customer's storage is unpriced".
     */
    private function issue(string $message, ?int $customerId = null, ?string $category = null): void
    {
        $this->issues[] = $message;

        if ($customerId !== null && $category !== null) {
            $this->cellIssues[$customerId][$category][$message] = true;
        }
    }

    /** Whole days between two `Y-m-d` dates; negative when `$b` precedes `$a`. */
    private static function daysBetween(string $a, string $b): int
    {
        $from = new DateTimeImmutable($a);
        $to   = new DateTimeImmutable($b);
        $diff = $from->diff($to);

        return (int) $diff->days * ($diff->invert ? -1 : 1);
    }

    /** Matches `StorageHandlingController::normalizeSize` so both price alike. */
    private static function normalizeSize(string $size): string
    {
        $num = (int) preg_replace('/\D/', '', $size);

        return in_array($num, [20, 40, 45], true) ? (string) $num : '';
    }
}
