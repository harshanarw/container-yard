<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardJobType;
use App\Services\ContainerInquiryService;
use App\Services\ContainerMrStatusService;
use App\Support\Export\GateMovementWorkbook;
use App\Support\Export\TabularExport;
use App\Support\MrStatusCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContainerInquiryController extends Controller
{
    public function __construct(private ContainerInquiryService $service)
    {
        $this->middleware('can:container-inquiry.view');
    }

    /**
     * Every filter the screen offers, in one place.
     *
     * It used to be written out twice -- once here and once in `export()` --
     * and the second copy fell three filters behind: `movement_scope`,
     * `vehicle_plate` and `driver_name` were never read, so setting them
     * narrowed the screen and did nothing to the file. A list repeated is a list
     * that goes stale, so there is now one.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->only([
            'container_no', 'customer_id', 'job_type_code', 'job_no',
            'date_from', 'date_to', 'movement_scope', 'status',
            'vessel_name', 'voyage_no', 'bl_number', 'seal_no', 'eir_ref',
            'vehicle_plate', 'driver_name',
            'mr_status', 'mr_status_group', 'export_ready', 'on_hold',
        ]);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $movements  = null;
        $gateOutMap = [];

        // `movement_scope` is excluded deliberately: on its own it narrows
        // nothing -- it only says which gate the dates are measured against --
        // so arriving with it set is not a search. The Gate Movements menu
        // entry does exactly that, and without this it would load every
        // movement ever recorded on a bare page open.
        $searched = $request->hasAny(array_diff(array_keys($filters), ['movement_scope']));

        if ($searched) {
            $movements  = $this->service->search($filters);
            $gateOutMap = $this->service->matchGateOutsForPage($movements->getCollection());
        }

        $customers = Customer::selectable()->where('status', 'active')->orderBy('name')->get();
        $jobTypes  = YardJobType::active()->orderBy('sort_order')->get();

        // Grouped by lane so the dropdown reads as the workflow it describes,
        // rather than 25 flat options.
        $mrStatusesByLane = MrStatusCatalogue::codesByLane();
        $mrStatusGroups   = MrStatusCatalogue::groups();

        return view('container-inquiry.index', compact(
            'movements', 'filters', 'searched', 'customers', 'jobTypes', 'gateOutMap',
            'mrStatusesByLane', 'mrStatusGroups'
        ));
    }

    public function show(string $containerNo, ContainerMrStatusService $mrStatus)
    {
        $data = $this->service->getContainerHistory($containerNo);

        if ($data['cycles']->isEmpty() && !$data['container']) {
            return redirect()->route('container-inquiry.index')
                ->with('warning', "No records found for container number: {$containerNo}");
        }

        // Self-healing read: one container, already loaded, so re-deriving costs
        // little — and it guarantees that the screen an operator opens to check
        // a specific box is never showing a stale badge. One pass both corrects
        // the stored projection and gives us the value to render; it writes only
        // when something actually differs.
        $data['mrStatus'] = $data['container']
            ? $mrStatus->resolveAndSync($data['container'])
            : null;

        return view('container-inquiry.show', $data);
    }

    public function print(string $containerNo, ContainerMrStatusService $mrStatus)
    {
        $data = $this->service->getContainerHistory($containerNo);

        if ($data['cycles']->isEmpty() && !$data['container']) {
            return redirect()->route('container-inquiry.index')
                ->with('warning', "No records found for container number: {$containerNo}");
        }

        // Derived, not corrected: a print view should not have side effects.
        $data['mrStatus'] = $data['container']
            ? $mrStatus->forContainer($data['container'])
            : null;

        return view('container-inquiry.print', $data);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $q = strtoupper(trim($request->get('q', '')));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $fromContainers = Container::where('container_no', 'LIKE', $q . '%')
            ->orderBy('container_no')
            ->limit(15)
            ->pluck('container_no');

        $fromMovements = GateMovement::where('container_no', 'LIKE', $q . '%')
            ->where('movement_type', 'in')
            ->distinct()
            ->orderBy('container_no')
            ->limit(15)
            ->pluck('container_no');

        $results = $fromContainers->merge($fromMovements)->unique()->sort()->values();

        return response()->json($results);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        return TabularExport::stream($request->input('format'), 'container-inquiry', [
            'EIR Ref', 'Container No', 'Customer', 'Job No', 'Job Type',
            'Gate In', 'Gate Out', 'Days In Yard',
            'Job Status',
            'M&R Status', 'M&R Stage Age (days)', 'Export Ready', 'On Hold',
            'Condition On Arrival', 'Size', 'Cargo Status',
            'Vessel', 'Voyage No', 'BL Number', 'Seal No',
        ], function () use ($filters) {
            $query = $this->service->query($filters)
                ->with(['yardJob.jobType', 'customer',
                        'container:id,export_ready,mr_status_expires_at',
                        'container.activeHolds:id,container_id,hold_type']);

            // A chunk at a time, not a row at a time: the gate-out lookup below
            // is batched per chunk, and flattening this to one row per query
            // would turn the export into an N+1.
            foreach ($query->lazy(200)->chunk(200) as $chunk) {
                // `lazy()->chunk()` yields LazyCollections, and the matcher
                // needs a materialised one -- it walks each container's
                // gate-ins twice to bound them by the next arrival.
                $items = $chunk->collect();

                // The same matcher the screen uses, batched over the chunk. The
                // copy that stood here paired greedily with no record of which
                // gate-outs it had already spent, so a container with two visits
                // inside one chunk had the earlier departure answer for both.
                $gateOutMap = $this->service->matchGateOutsForPage($items);

                foreach ($items as $m) {
                    $gateOut = $gateOutMap[$m->id] ?? null;

                    $gateInTime  = $m->gate_in_time?->format('Y-m-d H:i') ?? '-';
                    $gateOutTime = $gateOut?->gate_out_time?->format('Y-m-d H:i') ?? '-';
                    $daysInYard  = \App\Support\DaysInYard::between(
                        $m->gate_in_time, $gateOut?->gate_out_time
                    ) ?? '-';

                    // Days in the current M&R stage — distinct from days in
                    // yard: a box can sit five days in the yard and four of
                    // them waiting on QC.
                    $stageAge = $m->mr_status_at
                        ? (int) $m->mr_status_at->diffInDays(now())
                        : '-';

                    yield [
                        $m->id,
                        $m->container_no,
                        optional($m->customer)->name ?? '-',
                        optional($m->yardJob)->job_no ?? '-',
                        optional(optional($m->yardJob)->jobType)->job_type_name ?? $m->job_type_code ?? '-',
                        $gateInTime,
                        $gateOutTime,
                        $daysInYard,
                        optional($m->yardJob)->status ?? '-',
                        $m->mr_status ? MrStatusCatalogue::label($m->mr_status) : '-',
                        $stageAge,
                        $m->container ? ($m->container->export_ready && ! $m->container->mrStatusHasExpired() ? 'Yes' : 'No') : '-',
                        $m->container?->activeHolds->isNotEmpty() ? 'Yes' : 'No',
                        $m->condition ?? '-',
                        $m->size ?? '-',
                        $m->cargo_status ?? '-',
                        $m->vessel_name ?? '-',
                        $m->voyage_no ?? '-',
                        $m->bl_number ?? '-',
                        $m->seal_no ?? '-',
                    ];
                }
            }
        });
    }

    /**
     * The same search, exported for the other audience.
     *
     * `export()` above is the M&R file: stage age, export readiness, holds.
     * This one is the gate log -- both trucks, both drivers, the BL and the day
     * count -- for whoever is settling a damage claim or a gate dispute. One
     * query, one set of rows, two column sets, because those are two jobs and
     * neither reader wants the other's columns.
     *
     * Deliberately not a second screen: the filters, the pairing and the
     * customer resolution stay in one place, which is the whole reason the
     * report was extended rather than duplicated.
     */
    public function gateLog(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        if (! GateMovementWorkbook::available()) {
            // An older openspout on the host means no styling, not no file.
            return $this->export($request);
        }

        $query = fn () => $this->service->query($filters);

        return GateMovementWorkbook::stream(
            $this->gateLogRows($query()),
            [
                'period'  => $this->periodLabel($filters),
                'scope'   => ContainerInquiryService::MOVEMENT_SCOPES[$filters['movement_scope'] ?? 'either']
                    ?? ContainerInquiryService::MOVEMENT_SCOPES['either'],
                'filters' => $this->filterSummary($filters),
                // A COUNT over the same conditions. The rows themselves are
                // streamed, so they cannot be counted without being held.
                'visits'  => $query()->count(),
            ],
        );
    }

    /**
     * One positional row per visit, matching GateMovementWorkbook::HEADINGS.
     *
     * @return \Generator<int, array<int, mixed>>
     */
    private function gateLogRows($query): \Generator
    {
        $query = $query->with(['yardJob.jobType', 'customer']);

        foreach ($query->lazy(200)->chunk(200) as $chunk) {
            $items      = $chunk->collect();
            $gateOutMap = $this->service->matchGateOutsForPage($items);

            foreach ($items as $m) {
                $out  = $gateOutMap[$m->id] ?? null;
                $days = \App\Support\DaysInYard::between($m->gate_in_time, $out?->gate_out_time);

                yield [
                    $m->container_no,
                    $m->size ?? '',
                    $m->container_type ?? '',
                    $m->cargo_status ? ucfirst($m->cargo_status) : '',
                    $m->customer?->name ?? '',
                    $m->yardJob?->job_no ?? '',
                    $m->yardJob?->jobType?->job_type_name ?? $m->job_type_code ?? '',
                    $m->gate_in_time?->format('Y-m-d H:i') ?? '',
                    $m->vehicle_plate ?? '',
                    $m->driver_name ?? '',
                    $out?->gate_out_time?->format('Y-m-d H:i') ?? '',
                    $out?->vehicle_plate ?? '',
                    $out?->driver_name ?? '',
                    // A real number so the column sums and sorts; blank rather
                    // than zero where there is no departure to count to, since
                    // a 0 would read as "in and out the same day".
                    $days === null ? '' : (int) $days,
                    $out ? 'Departed' : 'In Yard',
                    $m->bl_number ?? '',
                    trim(($m->vessel_name ?? '') . ' ' . ($m->voyage_no ?? '')),
                    // Read from the *departure*: the rental is what took the box
                    // out, and the arrival that opened the visit knows nothing
                    // about it. Blank on every ordinary visit.
                    $out?->heldByAnotherParty() ? $out->holdingParty()?->name : '',
                    $out?->yardJob?->job_type_code === 'CONTAINER_RELET'
                        ? $out->yardJob->job_no
                        : '',
                ];
            }
        }
    }

    /** The date window in words, for the sheet's header block. */
    private function periodLabel(array $filters): string
    {
        $from = $filters['date_from'] ?? null;
        $to   = $filters['date_to']   ?? null;

        $fmt = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M Y');

        return match (true) {
            $from && $to => $fmt($from) . '  to  ' . $fmt($to),
            (bool) $from => 'From ' . $fmt($from),
            (bool) $to   => 'Up to ' . $fmt($to),
            default      => 'All dates',
        };
    }

    /**
     * The non-date filters, named, so a forwarded sheet still says what it is.
     *
     * A spreadsheet that has been emailed on twice has lost the screen it came
     * from, and "why is this box missing" is unanswerable without knowing what
     * was narrowed.
     */
    private function filterSummary(array $filters): string
    {
        $labels = [
            'container_no'  => 'Container',
            'job_no'        => 'Job No',
            'job_type_code' => 'Job Type',
            'status'        => 'Job Status',
            'vessel_name'   => 'Vessel',
            'voyage_no'     => 'Voyage',
            'bl_number'     => 'BL',
            'seal_no'       => 'Seal',
            'vehicle_plate' => 'Vehicle',
            'driver_name'   => 'Driver',
            'mr_status'     => 'M&R Status',
        ];

        $parts = [];

        if (! empty($filters['customer_id'])) {
            $parts[] = 'Customer: ' . (Customer::find($filters['customer_id'])?->name ?? $filters['customer_id']);
        }

        foreach ($labels as $key => $label) {
            if (! empty($filters[$key])) {
                $parts[] = $label . ': ' . $filters[$key];
            }
        }

        foreach (['export_ready' => 'Export ready', 'on_hold' => 'On hold'] as $key => $label) {
            if (! empty($filters[$key])) {
                $parts[] = $label;
            }
        }

        return implode('   |   ', $parts);
    }
}
