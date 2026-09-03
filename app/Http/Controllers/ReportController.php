<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardStorage;
use App\Services\ContainerMrStatusService;
use App\Services\Reporting\WeekBreakdown;
use App\Services\Reporting\WeeklyPerformanceReport;
use App\Support\Export\TabularExport;
use App\Support\Export\WeeklyPerformanceWorkbook;
use App\Support\MrStatusCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:reports.view');
    }

    /**
     * The inventory query, defined once.
     *
     * The screen and the export read it, so an operator who filters the screen
     * and then exports cannot be handed the unfiltered set — the commonest bug
     * in this kind of feature, and a silent one.
     */
    private function inventoryQuery(Request $request)
    {
        return Container::with('customer')
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->status,      fn ($q, $v) => $q->where('status', $v))
            ->when($request->size,        fn ($q, $v) => $q->where('size', $v))
            ->when($request->condition,   fn ($q, $v) => $q->where('condition', $v))
            ->when($request->mr_status_group, fn ($q, $v) => $q->where('mr_status_group', $v))
            ->when($request->date_from,   fn ($q, $v) => $q->whereDate('gate_in_date', '>=', $v))
            ->when($request->date_to,     fn ($q, $v) => $q->whereDate('gate_in_date', '<=', $v))
            ->orderBy('gate_in_date', 'desc');
    }

    public function inventory(Request $request)
    {
        $containers = $this->inventoryQuery($request)->get();

        $summary = [
            'total'        => $containers->count(),
            'available'    => $containers->where('status', 'available')->count(),
            'in_yard'      => $containers->where('status', 'in_yard')->count(),
            'in_repair'    => $containers->where('status', 'in_repair')->count(),
            'reserved'     => $containers->where('status', 'reserved')->count(),
            'released'     => $containers->where('status', 'released')->count(),
            'by_size_20'   => $containers->where('size', '20')->count(),
            'by_size_40'   => $containers->where('size', '40')->count(),
            'by_size_45'   => $containers->where('size', '45')->count(),
        ];

        // Beside the disposition tiles, not instead of them: the two answer
        // different questions — where each box is, versus what it is waiting on.
        $mrSummary = collect(MrStatusCatalogue::groups())
            ->map(fn ($label, $key) => [
                'label' => $label,
                'count' => $containers->where('mr_status_group', $key)->count(),
            ])
            ->except([MrStatusCatalogue::GROUP_CLOSED]);

        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $mrStatusGroups = MrStatusCatalogue::groups();

        return view('reports.inventory', compact('containers', 'summary', 'customers', 'mrSummary', 'mrStatusGroups'));
    }

    /**
     * Inventory, as a file.
     *
     * Mirrors the columns on screen, with the badges resolved to the words they
     * stand for — a spreadsheet cannot show a colour, and "Require Repair" is
     * what the operator reading the file needs.
     */
    public function exportInventory(Request $request)
    {
        $query = $this->inventoryQuery($request);

        return TabularExport::stream($request->input('format'), 'inventory', [
            'Container No', 'Size', 'Type', 'Customer Code', 'Customer',
            'Condition', 'Cargo', 'Location', 'Gate In Date', 'Days In Yard',
            'Status', 'M&R Status', 'Stage',
        ], function () use ($query) {
            foreach ($query->lazy(200) as $c) {
                // The screen counts to today while a box is still here, and to
                // the gate-out once it has left.
                $days = match (true) {
                    $c->gate_in_date && ! $c->gate_out_date => (int) $c->gate_in_date->diffInDays(now()),
                    (bool) $c->gate_out_date                => (int) $c->gate_in_date?->diffInDays($c->gate_out_date),
                    default                                 => null,
                };

                $location = $c->location_row
                    ? $c->location_row . $c->location_bay . '-T' . $c->location_tier
                    : '-';

                yield [
                    $c->container_no,
                    $c->size ?? '-',
                    $c->type_code ?? '-',
                    $c->customer->code ?? '-',
                    $c->customer->name ?? '-',
                    match ($c->condition) {
                        'sound'          => 'Sound',
                        'damaged'        => 'Damaged',
                        'require_repair' => 'Require Repair',
                        default          => ucfirst((string) $c->condition),
                    },
                    $c->cargo_status === 'empty' ? 'Empty' : 'Laden',
                    $location,
                    $c->gate_in_date?->format('Y-m-d') ?? '-',
                    $days ?? '-',
                    $c->status,
                    $c->mr_status ? MrStatusCatalogue::label($c->mr_status, $c->mr_lane) : '-',
                    MrStatusCatalogue::groups()[$c->mr_status_group] ?? ($c->mr_status_group ?? '-'),
                ];
            }
        });
    }

    /**
     * The billing report, as a file.
     *
     * Amounts are written unformatted — no thousands separators — so they arrive
     * in the spreadsheet as numbers that can be summed rather than as text that
     * looks like numbers.
     */
    public function exportBilling(Request $request)
    {
        $query = $this->billingQuery($request);

        return TabularExport::stream($request->input('format'), 'billing-report', [
            'Container No', 'Customer', 'Gate In', 'Gate Out',
            'Total Days', 'Free Days', 'Chargeable Days',
            'Daily Rate', 'Subtotal', 'Tax', 'Total Charge',
        ], function () use ($query) {
            foreach ($query->lazy(200) as $r) {
                yield [
                    $r->container->container_no ?? '-',
                    $r->customer->name ?? '-',
                    $r->gate_in_date?->format('Y-m-d') ?? '-',
                    $r->gate_out_date?->format('Y-m-d') ?? '-',
                    (int) $r->total_days,
                    (int) $r->free_days,
                    (int) $r->chargeable_days,
                    number_format((float) $r->daily_rate, 2, '.', ''),
                    number_format((float) $r->subtotal, 2, '.', ''),
                    number_format((float) $r->tax_amount, 2, '.', ''),
                    number_format((float) $r->total_charge, 2, '.', ''),
                ];
            }
        });
    }

    /**
     * M&R Status — what is in the yard and what each container is waiting on.
     *
     * Reads the stored projection, so the whole report is indexed-column work:
     * the summary and breakdown are grouped aggregates that do not care how big
     * the yard is, and only the detail list is paginated. Nothing is derived
     * per row.
     *
     * There is no job-type filter here on purpose. Job type is the axis the
     * *lane* is derived from, and lane is stored on the container, so filtering
     * by lane asks the same question in an indexed way. Filtering by the exact
     * job type of a particular visit is a movement-level question, and
     * Container Inquiry already answers it — it filters by job type and M&R
     * status together.
     */
    public function mrStatus(Request $request, ContainerMrStatusService $mrStatus)
    {
        $filters = $request->only(['customer_id', 'size', 'mr_lane', 'mr_status', 'mr_status_group', 'overdue']);

        $thresholds = $mrStatus->ageThresholds();

        // Containers physically present. A released box is not "in the yard
        // waiting on" anything.
        $base = fn () => Container::whereIn('status', Container::IN_YARD_STATUSES)
            ->whereNotNull('mr_status')
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['size'] ?? null,        fn ($q, $v) => $q->where('size', $v))
            ->when($filters['mr_lane'] ?? null,     fn ($q, $v) => $q->where('mr_lane', $v))
            ->when($filters['mr_status'] ?? null,   fn ($q, $v) => $q->where('mr_status', $v))
            ->when($filters['mr_status_group'] ?? null, fn ($q, $v) => $q->where('mr_status_group', $v));

        // ── Group roll-up ────────────────────────────────────────────────────
        $groupCounts = $base()->groupBy('mr_status_group')
            ->selectRaw('mr_status_group, COUNT(*) as total')
            ->pluck('total', 'mr_status_group');

        $summary = collect(MrStatusCatalogue::groups())
            ->map(fn ($label, $key) => [
                'label' => $label,
                'count' => (int) ($groupCounts[$key] ?? 0),
            ])
            ->except([MrStatusCatalogue::GROUP_CLOSED]);

        // ── Per-status breakdown, with ageing ────────────────────────────────
        //
        // Two different questions, both worth answering: the absolute shape of
        // the ageing (how long things sit), and the policy breach (how many are
        // past the threshold for *their* stage). A status with a ten-day
        // threshold and one with three are not comparable on days alone.
        $rows = $base()
            ->groupBy('mr_status', 'mr_lane')
            ->selectRaw('mr_status, mr_lane, COUNT(*) as total')
            ->selectRaw('AVG(DATEDIFF(?, mr_status_at)) as avg_days', [now()->toDateString()])
            ->selectRaw('MAX(DATEDIFF(?, mr_status_at)) as max_days', [now()->toDateString()])
            ->selectRaw('SUM(DATEDIFF(?, mr_status_at) <= 7) as band_week',      [now()->toDateString()])
            ->selectRaw('SUM(DATEDIFF(?, mr_status_at) BETWEEN 8 AND 14) as band_fortnight', [now()->toDateString()])
            ->selectRaw('SUM(DATEDIFF(?, mr_status_at) BETWEEN 15 AND 30) as band_month', [now()->toDateString()])
            ->selectRaw('SUM(DATEDIFF(?, mr_status_at) > 30) as band_over',      [now()->toDateString()])
            ->get()
            ->map(function ($row) use ($thresholds) {
                $threshold = $thresholds[$row->mr_status] ?? null;

                return [
                    'code'       => $row->mr_status,
                    'lane'       => $row->mr_lane,
                    'label'      => MrStatusCatalogue::label($row->mr_status, $row->mr_lane),
                    'group'      => MrStatusCatalogue::group($row->mr_status),
                    'badge'      => MrStatusCatalogue::badgeClass($row->mr_status),
                    'count'      => (int) $row->total,
                    'avg_days'   => (int) round((float) $row->avg_days),
                    'max_days'   => (int) $row->max_days,
                    'threshold'  => $threshold,
                    'bands'      => [
                        '≤7d'    => (int) $row->band_week,
                        '8–14d'  => (int) $row->band_fortnight,
                        '15–30d' => (int) $row->band_month,
                        '>30d'   => (int) $row->band_over,
                    ],
                ];
            })
            ->sortByDesc('count')
            ->values();

        // Overdue is per-stage, so it cannot be one SQL predicate across all
        // statuses — one OR-group per configured threshold.
        //
        // The guard matters: an empty group compiles to no constraint at all,
        // which would report the entire yard as overdue rather than none of it.
        $overdueQuery = $base()->where(function ($q) use ($thresholds) {
            if (empty($thresholds)) {
                $q->whereRaw('1 = 0');

                return;
            }

            foreach ($thresholds as $code => $days) {
                $q->orWhere(fn ($s) => $s->where('mr_status', $code)
                    ->whereRaw('DATEDIFF(?, mr_status_at) > ?', [now()->toDateString(), $days]));
            }
        });

        $overdueByStatus = (clone $overdueQuery)->groupBy('mr_status')
            ->selectRaw('mr_status, COUNT(*) as total')
            ->pluck('total', 'mr_status');

        $rows = $rows->map(function ($row) use ($overdueByStatus) {
            $row['overdue'] = (int) ($overdueByStatus[$row['code']] ?? 0);

            return $row;
        });

        $overdueTotal = (int) $overdueByStatus->sum();

        // ── Detail ───────────────────────────────────────────────────────────
        $detail = (! empty($filters['overdue']) ? $overdueQuery : $base())
            ->with('customer')
            ->withCount(['holds as active_holds_count' => fn ($q) => $q->whereNull('cleared_at')])
            ->orderBy('mr_status_at')     // longest-stuck first — the point of the report
            ->paginate(50)
            ->withQueryString();

        $customers        = Customer::where('status', 'active')->orderBy('name')->get();
        $mrStatusesByLane = MrStatusCatalogue::codesByLane();
        $mrStatusGroups   = MrStatusCatalogue::groups();

        return view('reports.mr-status', compact(
            'summary', 'rows', 'detail', 'filters', 'customers',
            'mrStatusesByLane', 'mrStatusGroups', 'thresholds', 'overdueTotal'
        ));
    }

    /**
     * The same rows as the report, streamed and unpaginated.
     *
     * Chunked rather than ->get(): a report the whole yard fits in is exactly
     * the one someone exports on the day the yard is full.
     */
    public function exportMrStatusCsv(Request $request, ContainerMrStatusService $mrStatus)
    {
        $thresholds = $mrStatus->ageThresholds();
        $today      = now()->toDateString();

        $query = Container::whereIn('status', Container::IN_YARD_STATUSES)
            ->whereNotNull('mr_status')
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->size,        fn ($q, $v) => $q->where('size', $v))
            ->when($request->mr_lane,     fn ($q, $v) => $q->where('mr_lane', $v))
            ->when($request->mr_status,   fn ($q, $v) => $q->where('mr_status', $v))
            ->when($request->mr_status_group, fn ($q, $v) => $q->where('mr_status_group', $v))
            ->when($request->boolean('overdue'), fn ($q) => $q->where(function ($s) use ($thresholds, $today) {
                if (empty($thresholds)) {
                    $s->whereRaw('1 = 0');   // no thresholds means nothing is overdue

                    return;
                }

                foreach ($thresholds as $code => $days) {
                    $s->orWhere(fn ($w) => $w->where('mr_status', $code)
                        ->whereRaw('DATEDIFF(?, mr_status_at) > ?', [$today, $days]));
                }
            }))
            ->with('customer')
            ->withCount(['holds as active_holds_count' => fn ($q) => $q->whereNull('cleared_at')])
            ->orderBy('mr_status_at');

        return TabularExport::stream($request->input('format'), 'mr-status', [
            'Container No', 'Customer', 'Size', 'Type',
            'Disposition', 'M&R Status', 'Stage', 'Lane',
            'In Stage Since', 'Days In Stage', 'Threshold (days)', 'Overdue',
            'On Hold', 'Export Ready',
        ], function () use ($query, $thresholds) {
            // lazy() pages the query exactly as chunk() did — the yard is not
            // held in memory to write a file about it.
            foreach ($query->lazy(200) as $c) {
                $days      = $c->mr_status_at ? (int) $c->mr_status_at->diffInDays(now()) : null;
                $threshold = $thresholds[$c->mr_status] ?? null;

                yield [
                    $c->container_no,
                    $c->customer->name ?? '-',
                    $c->size ?? '-',
                    $c->type_code ?? '-',
                    $c->status,
                    MrStatusCatalogue::label($c->mr_status, $c->mr_lane),
                    MrStatusCatalogue::groups()[$c->mr_status_group] ?? $c->mr_status_group,
                    MrStatusCatalogue::laneLabel($c->mr_lane),
                    $c->mr_status_at?->format('Y-m-d H:i') ?? '-',
                    $days ?? '-',
                    $threshold ?? '-',
                    ($threshold !== null && $days !== null && $days > $threshold) ? 'Yes' : 'No',
                    ($c->active_holds_count ?? 0) > 0 ? 'Yes' : 'No',
                    ($c->export_ready && ! $c->mrStatusHasExpired()) ? 'Yes' : 'No',
                ];
            }
        });
    }

    /** Defined once, for the same reason as the inventory query above. */
    private function billingQuery(Request $request)
    {
        return YardStorage::with(['container', 'customer'])
            ->nonHire()  // exclude on_hire records (zero-rated, hire customer billed separately)
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->date_from,   fn ($q, $v) => $q->whereDate('gate_in_date', '>=', $v))
            ->when($request->date_to,     fn ($q, $v) => $q->whereDate('gate_out_date', '<=', $v))
            ->whereNotNull('gate_out_date')
            ->orderBy('gate_out_date', 'desc');
    }

    public function billing(Request $request)
    {
        $storageRecords = $this->billingQuery($request)->get();

        $summary = [
            'total_records'    => $storageRecords->count(),
            'total_revenue'    => $storageRecords->sum('total_charge'),
            'total_days'       => $storageRecords->sum('total_days'),
            'avg_stay'         => $storageRecords->avg('total_days'),
        ];

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('reports.billing', compact('storageRecords', 'summary', 'customers'));
    }

    /**
     * Weekly Performance — lifts per customer, per week, by size and cargo
     * status, with Mounting and Demounting on separate rows.
     *
     * Authorization is the constructor's `can:reports.view` and is not repeated
     * here. That is the opposite of the finance controllers, which authorize
     * per action; the check belongs in exactly one of the two places and this
     * controller has chosen the constructor.
     */
    public function weeklyPerformance(Request $request, WeeklyPerformanceReport $report)
    {
        $filters = $this->weeklyPerformanceFilters($request);

        // One call. The grid, and later the workbook, read the same array —
        // there is no second query that could drift from what the operator
        // filtered the screen to.
        $data = $report->build($filters['from'], $filters['to'], $filters);

        return view('reports.weekly-performance', [
            'data'      => $data,
            'filters'   => $filters,
            'weekRules' => WeekBreakdown::rules(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * The sheet as the workbook the yard already circulates — merged week
     * bands, the date range under each, blank zeros.
     */
    public function exportWeeklyPerformance(Request $request, WeeklyPerformanceReport $report)
    {
        $filters = $this->weeklyPerformanceFilters($request);
        $data    = $report->build($filters['from'], $filters['to'], $filters);

        // No writer, or one too old for the banded layout: fall back to the
        // flat CSV rather than 500, or than hand back an unstyled sheet that
        // would look nothing like the one the yard circulates. The button on
        // the screen asks the same question, so this is the second line of
        // defence rather than the first.
        if (! WeeklyPerformanceWorkbook::available()) {
            return $this->weeklyPerformanceCsv($data);
        }

        return WeeklyPerformanceWorkbook::stream($data);
    }

    /**
     * The same figures flattened to one heading row per column.
     *
     * A merged workbook is unreadable to a script, and not everything that
     * consumes this report is Excel. Zero is written as `0` here rather than
     * left blank: this file exists to be parsed, and a blank cell is not a
     * number.
     */
    public function exportWeeklyPerformanceCsv(Request $request, WeeklyPerformanceReport $report)
    {
        $filters = $this->weeklyPerformanceFilters($request);

        return $this->weeklyPerformanceCsv($report->build($filters['from'], $filters['to'], $filters));
    }

    private function weeklyPerformanceCsv(array $data)
    {
        $headings = ['Customer', 'Code', 'Direction'];
        foreach (array_merge($data['weeks'], [['no' => 'Total', 'label' => '']]) as $week) {
            foreach ($data['columns'] as $key) {
                [$status, $size] = explode('_', $key);
                $band = is_int($week['no']) ? "W{$week['no']} {$week['label']}" : 'Total';
                $headings[] = $band . ' · ' . ucfirst($status) . ' ' . $size;
            }
        }

        return TabularExport::csv('weekly-performance', $headings, function () use ($data) {
            $line = function (string $label, string $code, string $direction, array $side) use ($data) {
                $row = [$label, $code, $direction];
                foreach (array_keys($data['weeks']) as $w) {
                    foreach ($data['columns'] as $key) {
                        $row[] = (int) ($side['weeks'][$w][$key] ?? 0);
                    }
                }
                foreach ($data['columns'] as $key) {
                    $row[] = (int) ($side['total'][$key] ?? 0);
                }

                return $row;
            };

            foreach ($data['rows'] as $entry) {
                yield $line($entry['customer'], (string) $entry['code'], 'Demounting', $entry['demounting']);
                yield $line($entry['customer'], (string) $entry['code'], 'Mounting', $entry['mounting']);
            }

            yield $line('TOTAL DEMOUNTING', '', 'Demounting', $data['totals']['demounting']);
            yield $line('TOTAL MOUNTING', '', 'Mounting', $data['totals']['mounting']);
            yield $line('GRAND TOTAL', '', 'Both', $data['totals']['grand']);
        });
    }

    /**
     * The filters, read once and normalised.
     *
     * Defaults to the current month, which is the period this sheet is
     * circulated for. `validate()` rather than trust: `week_rule` reaches an
     * array lookup and `customer_id` a query, and a report screen's query
     * string is as reachable as any other.
     *
     * @return array{from:string,to:string,week_rule:string,customer_id:?int,only_with_movements:bool}
     */
    private function weeklyPerformanceFilters(Request $request): array
    {
        $request->validate([
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'week_rule'   => 'nullable|string|in:' . implode(',', array_keys(WeekBreakdown::rules())),
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        return [
            'from'                => $request->input('from', now()->startOfMonth()->toDateString()),
            'to'                  => $request->input('to', now()->endOfMonth()->toDateString()),
            'week_rule'           => $request->input('week_rule', WeekBreakdown::DEFAULT),
            'customer_id'         => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            'only_with_movements' => $request->boolean('only_with_movements'),
        ];
    }

    public function dailyMovements(Request $request)
    {
        $exportFilter = $request->input('export_status', 'pending');

        $query = GateMovement::with(['customer', 'createdBy'])
            ->when($request->customer_id,  fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->movement_type, fn ($q, $v) => $q->where('movement_type', $v))
            ->when($request->date_from, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->whereDate('gate_in_time', '>=', $v)
                        ->orWhereDate('gate_out_time', '>=', $v);
                });
            })
            ->when($request->date_to, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->whereDate('gate_in_time', '<=', $v)
                        ->orWhereDate('gate_out_time', '<=', $v);
                });
            })
            ->when($request->time_from, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->whereTime('gate_in_time', '>=', $v)
                        ->orWhereTime('gate_out_time', '>=', $v);
                });
            })
            ->when($request->time_to, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->whereTime('gate_in_time', '<=', $v)
                        ->orWhereTime('gate_out_time', '<=', $v);
                });
            });

        // Export status filter
        if ($exportFilter === 'pending') {
            // Pending = never exported in any format (both null)
            $query->whereNull('codeco_exported_at')->whereNull('csv_exported_at');
        } elseif ($exportFilter === 'exported') {
            // Exported = at least one format has been exported
            $query->where(function ($q) {
                $q->whereNotNull('codeco_exported_at')->orWhereNotNull('csv_exported_at');
            });
        }
        // 'all' → no filter

        $movements = $query->orderBy('gate_in_time', 'desc')->get();

        // Group by customer (Container Operator / Liner)
        $grouped = $movements->groupBy(fn ($m) => $m->customer_id ?? 0);

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('reports.daily-movements', compact('movements', 'grouped', 'customers', 'exportFilter'));
    }

    public function exportMovementsCsv(Request $request)
    {
        $ids = $request->input('movement_ids', []);
        if (empty($ids)) {
            return back()->with('error', 'No movements selected for export.');
        }

        $movements = GateMovement::with(['customer', 'createdBy'])
            ->whereIn('id', $ids)
            ->orderBy('customer_id')
            ->orderBy('gate_in_time')
            ->get();

        $batchRef = 'CSV-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4));
        $userId   = Auth::id();
        $now      = now();

        // Mark as exported
        GateMovement::whereIn('id', $ids)->update([
            'csv_exported_at'  => $now,
            'csv_exported_by'  => $userId,
            'csv_batch_ref'    => $batchRef,
        ]);

        return TabularExport::stream($request->input('format'), 'daily-movements', [
            'Batch Ref', 'Movement Type', 'Container No', 'Size', 'Equipment Type',
            'Container Operator', 'Condition', 'M&R Status', 'Cargo Status', 'Seal No',
            'Vehicle Plate', 'Driver Name', 'Driver IC', 'Release Order',
            'Gate In Date/Time', 'Gate Out Date/Time',
            'Location Row', 'Location Bay', 'Location Tier',
            'Remarks', 'Recorded By',
        ], function () use ($movements) {
            // Already loaded: this export covers the rows the operator ticked,
            // so it is bounded by the selection rather than by the table.
            foreach ($movements as $m) {
                yield [
                    $m->csv_batch_ref,
                    strtoupper($m->movement_type),
                    $m->container_no,
                    $m->size,
                    $m->container_type,
                    $m->customer->name ?? '—',
                    $m->condition,
                    // That cycle's status, from the movement row itself — a
                    // gate-out row carries none, since only gate-ins own a cycle.
                    $m->mr_status ? MrStatusCatalogue::label($m->mr_status, $m->mr_lane) : '',
                    $m->cargo_status,
                    $m->seal_no,
                    $m->vehicle_plate,
                    $m->driver_name,
                    $m->driver_ic,
                    $m->release_order,
                    $m->gate_in_time?->format('Y-m-d H:i:s'),
                    $m->gate_out_time?->format('Y-m-d H:i:s'),
                    $m->location_row,
                    $m->location_bay,
                    $m->location_tier,
                    $m->remarks,
                    $m->createdBy->name ?? '—',
                ];
            }
        });
    }

    public function exportMovementsCodeco(Request $request)
    {
        $ids = $request->input('movement_ids', []);
        if (empty($ids)) {
            return back()->with('error', 'No movements selected for export.');
        }

        $movements = GateMovement::with(['customer'])
            ->whereIn('id', $ids)
            ->orderBy('customer_id')
            ->orderBy('gate_in_time')
            ->get();

        $batchRef = 'CDCO-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4));
        $userId   = Auth::id();
        $now      = now();

        GateMovement::whereIn('id', $ids)->update([
            'codeco_exported_at' => $now,
            'codeco_exported_by' => $userId,
            'codeco_batch_ref'   => $batchRef,
        ]);

        $content  = $this->buildCodecoMessage($movements, $batchRef);
        $filename = 'CODECO-' . now()->format('Ymd-His') . '.edi';

        return response($content, 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildCodecoMessage($movements, string $batchRef): string
    {
        $now       = now();
        $dateStr   = $now->format('ymd');
        $timeStr   = $now->format('Hi');
        $msgRefNo  = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($batchRef)), 0, 14);
        $icRef     = str_pad($msgRefNo, 14, '0', STR_PAD_LEFT);

        $lines = [];

        // UNB — Interchange header
        $lines[] = "UNB+UNOA:2+CYMS+PARTNER+{$dateStr}:{$timeStr}+{$icRef}++CODECO'";

        $msgCount = 0;

        // Group by customer (operator)
        $grouped = $movements->groupBy('customer_id');

        foreach ($grouped as $customerId => $group) {
            $operator = $group->first()->customer;
            $msgCount++;
            $msgRef = str_pad($msgCount, 6, '0', STR_PAD_LEFT);
            $opCode = $operator ? strtoupper(substr(preg_replace('/\s+/', '', $operator->name), 0, 4)) : 'UNKN';

            // UNH — Message header
            $lines[] = "UNH+{$msgRef}+CODECO:D:96B:UN'";

            // BGM — Beginning of message (code 37 = container gate in/out)
            $lines[] = "BGM+37+{$msgRef}+9'";

            // DTM — Date/time of preparation
            $lines[] = "DTM+137:{$now->format('YmdHi')}:203'";

            // NAD — Name and address (operator)
            if ($operator) {
                $name = substr(str_replace("'", '', $operator->name), 0, 35);
                $lines[] = "NAD+CA+{$opCode}::ZZZ+{$name}'";
            }

            foreach ($group as $m) {
                $containerNo = preg_replace('/\s+/', '', strtoupper($m->container_no));

                // EQD — Equipment details
                $eqSize = $m->size === '20' ? '20G1' : ($m->size === '40' ? '40G1' : '45G1');
                $typeCode = $m->movement_type === 'in' ? '1' : '5'; // 1=full in, 5=full out (simplified)
                $lines[] = "EQD+CN+{$containerNo}+{$eqSize}::6+++{$typeCode}'";

                // TSR — Transport service requirements (cargo status)
                if ($m->cargo_status) {
                    $cargoCode = strtoupper($m->cargo_status) === 'LADEN' ? '1' : '4'; // 1=laden, 4=empty
                    $lines[] = "TSR+++{$cargoCode}'";
                }

                // DTM — Gate in or gate out date/time
                if ($m->movement_type === 'in' && $m->gate_in_time) {
                    $lines[] = "DTM+132:{$m->gate_in_time->format('YmdHi')}:203'";
                } elseif ($m->movement_type === 'out' && $m->gate_out_time) {
                    $lines[] = "DTM+133:{$m->gate_out_time->format('YmdHi')}:203'";
                }

                // SEL — Seal number
                if ($m->seal_no) {
                    $seal = substr($m->seal_no, 0, 10);
                    $lines[] = "SEL+{$seal}+ZZZ'";
                }

                // TDT — Transport details (vehicle plate)
                if ($m->vehicle_plate) {
                    $plate = substr(preg_replace('/\s+/', '', strtoupper($m->vehicle_plate)), 0, 17);
                    $lines[] = "TDT+20+{$plate}'";
                }

                // LOC — Location (yard position)
                if ($m->location_row || $m->location_bay) {
                    $loc = implode('-', array_filter([$m->location_row, $m->location_bay, $m->location_tier]));
                    $lines[] = "LOC+16+{$loc}::ZZZ'";
                }
            }

            // CNT — Control total (number of equipment)
            $lines[] = "CNT+16:{$group->count()}'";

            // UNT — Message trailer
            $segCount = count($lines) - ($msgCount - 1); // approximate; recalculated below
            $lines[] = "UNT+PLACEHOLDER+{$msgRef}'";
        }

        // UNZ — Interchange trailer
        $lines[] = "UNZ+{$msgCount}+{$icRef}'";

        // Recalculate UNT segment counts properly
        $output = $this->recalcUntSegments($lines);

        return implode("\n", $output) . "\n";
    }

    private function recalcUntSegments(array $lines): array
    {
        $result = [];
        $inMsg  = false;
        $segCnt = 0;
        $msgRef = '';
        $buffer = [];

        foreach ($lines as $line) {
            $tag = substr($line, 0, 3);

            if ($tag === 'UNH') {
                $inMsg  = true;
                $segCnt = 1;
                $buffer = [$line];
                preg_match('/UNH\+(\w+)\+/', $line, $m);
                $msgRef = $m[1] ?? '000001';
                continue;
            }

            if ($tag === 'UNT') {
                $segCnt++; // count UNT itself
                $buffer[] = "UNT+{$segCnt}+{$msgRef}'";
                foreach ($buffer as $b) {
                    $result[] = $b;
                }
                $inMsg  = false;
                $buffer = [];
                continue;
            }

            if ($inMsg) {
                $segCnt++;
                $buffer[] = $line;
            } else {
                $result[] = $line;
            }
        }

        return $result;
    }
}
