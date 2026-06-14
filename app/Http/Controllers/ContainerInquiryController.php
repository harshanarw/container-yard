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
        $filters = $request->only(['container_no', 'customer_id', 'job_type_code', 'job_no', 'date_from', 'date_to', 'status']);

        $movements   = null;
        $gateOutMap  = [];
        $searched    = $request->hasAny(array_keys($filters));

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

    public function autocomplete(Request $request): JsonResponse
    {
        $q = strtoupper(trim($request->get('q', '')));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        // Search containers master + fall back to historical gate movements
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
        $filters  = $request->only(['container_no', 'customer_id', 'job_type_code', 'job_no', 'date_from', 'date_to', 'status']);
        $filename = 'container-inquiry-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Container No', 'Customer', 'Job No', 'Job Type', 'Gate In', 'Job Status', 'Condition', 'Size', 'Cargo Status']);

            \App\Models\GateMovement::with(['yardJob.jobType', 'customer'])
                ->where('movement_type', 'in')
                ->when(!empty($filters['container_no']), fn ($q) => $q->where('container_no', 'LIKE', strtoupper(trim($filters['container_no'])) . '%'))
                ->when(!empty($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
                ->when(!empty($filters['job_type_code']), fn ($q) => $q->where('job_type_code', $filters['job_type_code']))
                ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('gate_in_time', '>=', $filters['date_from']))
                ->when(!empty($filters['date_to']),   fn ($q) => $q->whereDate('gate_in_time', '<=', $filters['date_to']))
                ->orderBy('gate_in_time', 'desc')
                ->chunk(200, function ($items) use ($output) {
                    foreach ($items as $m) {
                        fputcsv($output, [
                            $m->container_no,
                            optional($m->customer)->name ?? '-',
                            optional($m->yardJob)->job_no ?? '-',
                            optional(optional($m->yardJob)->jobType)->job_type_name ?? $m->job_type_code ?? '-',
                            $m->gate_in_time?->format('Y-m-d H:i') ?? '-',
                            optional($m->yardJob)->status ?? '-',
                            $m->condition ?? '-',
                            $m->size ?? '-',
                            $m->cargo_status ?? '-',
                        ]);
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
