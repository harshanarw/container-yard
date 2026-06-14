<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardJobType;
use App\Services\ContainerInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContainerInquiryController extends Controller
{
    public function __construct(private ContainerInquiryService $service)
    {
        $this->middleware('can:container-inquiry.view');
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'container_no', 'customer_id', 'job_type_code', 'job_no',
            'date_from', 'date_to', 'status',
            'vessel_name', 'voyage_no', 'bl_number', 'seal_no', 'eir_ref',
        ]);

        $movements  = null;
        $gateOutMap = [];
        $searched   = $request->hasAny(array_keys($filters));

        if ($searched) {
            $movements  = $this->service->search($filters);
            $gateOutMap = $this->service->matchGateOutsForPage($movements->getCollection());
        }

        $customers = Customer::where('status', 'active')->orderBy('name')->get();
        $jobTypes  = YardJobType::active()->orderBy('sort_order')->get();

        return view('container-inquiry.index', compact('movements', 'filters', 'searched', 'customers', 'jobTypes', 'gateOutMap'));
    }

    public function show(string $containerNo)
    {
        $data = $this->service->getContainerHistory($containerNo);

        if ($data['cycles']->isEmpty() && !$data['container']) {
            return redirect()->route('container-inquiry.index')
                ->with('warning', "No records found for container number: {$containerNo}");
        }

        return view('container-inquiry.show', $data);
    }

    public function print(string $containerNo)
    {
        $data = $this->service->getContainerHistory($containerNo);

        if ($data['cycles']->isEmpty() && !$data['container']) {
            return redirect()->route('container-inquiry.index')
                ->with('warning', "No records found for container number: {$containerNo}");
        }

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
        $filters  = $request->only([
            'container_no', 'customer_id', 'job_type_code', 'job_no',
            'date_from', 'date_to', 'status',
            'vessel_name', 'voyage_no', 'bl_number', 'seal_no', 'eir_ref',
        ]);
        $filename = 'container-inquiry-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'EIR Ref', 'Container No', 'Customer', 'Job No', 'Job Type',
                'Gate In', 'Gate Out', 'Days In Yard',
                'Job Status', 'Condition', 'Size', 'Cargo Status',
                'Vessel', 'Voyage No', 'BL Number', 'Seal No',
            ]);

            GateMovement::with(['yardJob.jobType', 'customer'])
                ->where('movement_type', 'in')
                ->when(!empty($filters['container_no']), fn ($q) => $q->where('container_no', 'LIKE', strtoupper(trim($filters['container_no'])) . '%'))
                ->when(!empty($filters['customer_id']),  fn ($q) => $q->where('customer_id', $filters['customer_id']))
                ->when(!empty($filters['job_type_code']), fn ($q) => $q->where('job_type_code', $filters['job_type_code']))
                ->when(!empty($filters['job_no']),       fn ($q) => $q->whereHas('yardJob', fn ($s) => $s->where('job_no', 'LIKE', '%' . trim($filters['job_no']) . '%')))
                ->when(!empty($filters['date_from']),    fn ($q) => $q->whereDate('gate_in_time', '>=', $filters['date_from']))
                ->when(!empty($filters['date_to']),      fn ($q) => $q->whereDate('gate_in_time', '<=', $filters['date_to']))
                ->when(!empty($filters['status']),       fn ($q) => $q->whereHas('yardJob', fn ($s) => $s->where('status', $filters['status'])))
                ->when(!empty($filters['vessel_name']),  fn ($q) => $q->where('vessel_name', 'LIKE', '%' . trim($filters['vessel_name']) . '%'))
                ->when(!empty($filters['voyage_no']),    fn ($q) => $q->where('voyage_no',   'LIKE', '%' . trim($filters['voyage_no'])   . '%'))
                ->when(!empty($filters['bl_number']),    fn ($q) => $q->where('bl_number',   'LIKE', '%' . trim($filters['bl_number'])   . '%'))
                ->when(!empty($filters['seal_no']),      fn ($q) => $q->where('seal_no',     'LIKE', '%' . trim($filters['seal_no'])     . '%'))
                ->when(!empty($filters['eir_ref']),      fn ($q) => $q->where('id', (int) $filters['eir_ref']))
                ->orderBy('gate_in_time', 'desc')
                ->chunk(200, function ($items) use ($output) {
                    // Batch-fetch gate-outs for this chunk
                    $containerNos = $items->pluck('container_no')->unique()->values()->all();
                    $gateOuts = GateMovement::where('movement_type', 'out')
                        ->whereIn('container_no', $containerNos)
                        ->orderBy('gate_out_time', 'asc')
                        ->get()
                        ->groupBy('container_no');

                    foreach ($items as $m) {
                        // Find the matching gate-out by yard_job_id or closest chronological
                        $cGateOuts = $gateOuts->get($m->container_no, collect());
                        $gateOut   = null;

                        if (!is_null($m->yard_job_id)) {
                            $gateOut = $cGateOuts->firstWhere('yard_job_id', $m->yard_job_id);
                        }
                        if (!$gateOut) {
                            $from = $m->gate_in_time?->timestamp ?? 0;
                            foreach ($cGateOuts as $go) {
                                if (is_null($go->yard_job_id) && ($go->gate_out_time?->timestamp ?? 0) >= $from) {
                                    $gateOut = $go;
                                    break;
                                }
                            }
                        }

                        $gateInTime  = $m->gate_in_time?->format('Y-m-d H:i') ?? '-';
                        $gateOutTime = $gateOut?->gate_out_time?->format('Y-m-d H:i') ?? '-';
                        $daysInYard  = $m->gate_in_time
                            ? (int) $m->gate_in_time->diffInDays($gateOut?->gate_out_time ?? now())
                            : '-';

                        fputcsv($output, [
                            $m->id,
                            $m->container_no,
                            optional($m->customer)->name ?? '-',
                            optional($m->yardJob)->job_no ?? '-',
                            optional(optional($m->yardJob)->jobType)->job_type_name ?? $m->job_type_code ?? '-',
                            $gateInTime,
                            $gateOutTime,
                            $daysInYard,
                            optional($m->yardJob)->status ?? '-',
                            $m->condition ?? '-',
                            $m->size ?? '-',
                            $m->cargo_status ?? '-',
                            $m->vessel_name ?? '-',
                            $m->voyage_no ?? '-',
                            $m->bl_number ?? '-',
                            $m->seal_no ?? '-',
                        ]);
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
