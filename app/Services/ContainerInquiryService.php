<?php

namespace App\Services;

use App\Models\Container;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\ReeferPlugSession;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use App\Models\YardStorage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ContainerInquiryService
{
    /**
     * Search gate-in movements with optional filters, one row per movement.
     */
    public function search(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return GateMovement::with(['yardJob.jobType', 'customer', 'createdBy'])
            ->where('movement_type', 'in')
            ->when(!empty($filters['container_no']), function ($q) use ($filters) {
                $q->where('container_no', 'LIKE', strtoupper(trim($filters['container_no'])) . '%');
            })
            ->when(!empty($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(!empty($filters['job_type_code']), fn ($q) => $q->where('job_type_code', $filters['job_type_code']))
            ->when(!empty($filters['job_no']), function ($q) use ($filters) {
                $q->whereHas('yardJob', fn ($sub) => $sub->where('job_no', 'LIKE', '%' . trim($filters['job_no']) . '%'));
            })
            ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('gate_in_time', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),   fn ($q) => $q->whereDate('gate_in_time', '<=', $filters['date_to']))
            ->when(!empty($filters['status']), function ($q) use ($filters) {
                $q->whereHas('yardJob', fn ($sub) => $sub->where('status', $filters['status']));
            })
            ->orderBy('gate_in_time', 'desc')
            ->paginate($perPage);
    }

    /**
     * Build gate-out map for a page of search results (index view).
     *
     * Loads all gate-out movements for the page's container numbers in one
     * batch query, then pairs each gate-in to its gate-out using the same
     * two-tier strategy as buildGateOutMap():
     *   1. yard_job_id match (records created via current workflow)
     *   2. chronological proximity (older records without yard_job_id)
     *
     * Returns: [ gate_in_id => GateMovement $gateOut ]
     */
    public function matchGateOutsForPage(Collection $gateIns): array
    {
        if ($gateIns->isEmpty()) {
            return [];
        }

        $containerNos = $gateIns->pluck('container_no')->unique()->values()->all();

        $allGateOuts = GateMovement::where('movement_type', 'out')
            ->whereIn('container_no', $containerNos)
            ->orderBy('gate_out_time', 'asc')
            ->get()
            ->groupBy('container_no');

        $map = [];
        foreach ($gateIns->groupBy('container_no') as $cno => $cGateIns) {
            $cGateOuts = $allGateOuts->get($cno, collect());
            $map += $this->buildGateOutMap($cGateIns, $cGateOuts);
        }

        return $map;
    }

    /**
     * Build the full history for a container number: profile + job cycles.
     */
    public function getContainerHistory(string $containerNo): array
    {
        $containerNo = strtoupper(trim($containerNo));

        $container = Container::where('container_no', $containerNo)
            ->with(['customer', 'equipmentType', 'grade'])
            ->first();

        // Gate-in movements ordered newest first (for display)
        $gateIns = GateMovement::with(['yardJob.jobType', 'customer', 'grade', 'createdBy'])
            ->where('container_no', $containerNo)
            ->where('movement_type', 'in')
            ->orderBy('gate_in_time', 'desc')
            ->get();

        // ALL gate-out movements for this container — includes both records
        // linked via yard_job_id and older records that have none
        $allGateOuts = GateMovement::with(['customer', 'createdBy'])
            ->where('container_no', $containerNo)
            ->where('movement_type', 'out')
            ->orderBy('gate_out_time', 'asc')
            ->get();

        // Pair each gate-in to its gate-out (by job ID or date proximity)
        $gateOutMap = $this->buildGateOutMap($gateIns, $allGateOuts);

        // All surveys for this container
        $inquiries = Inquiry::where('container_no', $containerNo)
            ->orderBy('created_at', 'desc')
            ->get();

        // All estimates for this container
        $estimates = Estimate::where('container_no', $containerNo)
            ->with(['createdBy'])
            ->orderBy('estimate_date', 'desc')
            ->get();

        // All work orders
        $workOrders = WorkOrder::where('container_no', $containerNo)
            ->with(['createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Storage records (keyed to container master record)
        $storageRecords = YardStorage::where('container_id', optional($container)->id)
            ->orderBy('gate_in_date', 'desc')
            ->get();

        // Reefer sessions
        $reeferSessions = ReeferPlugSession::where('container_id', optional($container)->id)
            ->orderBy('plug_in_at', 'desc')
            ->get();

        // Build job cycles — each gate-in is one cycle
        $cycles = $gateIns->map(function (GateMovement $gateIn) use (
            $gateOutMap, $inquiries, $estimates, $workOrders, $storageRecords, $reeferSessions
        ) {
            $gateOut = $gateOutMap[$gateIn->id] ?? null;
            $yardJob = $gateIn->yardJob;

            $cycleInquiries = $inquiries->filter(function ($inq) use ($gateIn, $gateOut) {
                return $this->withinCycle($inq->created_at, $gateIn->gate_in_time, optional($gateOut)->gate_out_time);
            })->values();

            $inquiryIds = $cycleInquiries->pluck('id');

            $cycleEstimates = $estimates->filter(function ($est) use ($inquiryIds, $gateIn, $gateOut) {
                return $inquiryIds->contains($est->inquiry_id)
                    || $this->withinCycle($est->estimate_date, $gateIn->gate_in_time, optional($gateOut)->gate_out_time);
            })->values();

            $estimateIds = $cycleEstimates->pluck('id');

            $cycleWorkOrders = $workOrders->filter(function ($wo) use ($estimateIds, $gateIn, $gateOut) {
                return $estimateIds->contains($wo->estimate_id)
                    || $this->withinCycle($wo->created_at, $gateIn->gate_in_time, optional($gateOut)->gate_out_time);
            })->values();

            $cycleStorage = $storageRecords->filter(function ($sr) use ($gateIn, $gateOut) {
                return $this->withinCycle($sr->gate_in_date, $gateIn->gate_in_time, optional($gateOut)->gate_out_time);
            })->values();

            $cycleReefer = $reeferSessions->filter(function ($rs) use ($gateIn, $gateOut) {
                return $this->withinCycle($rs->plug_in_at, $gateIn->gate_in_time, optional($gateOut)->gate_out_time);
            })->values();

            return [
                'gate_in'     => $gateIn,
                'gate_out'    => $gateOut,
                'yard_job'    => $yardJob,
                'inquiries'   => $cycleInquiries,
                'estimates'   => $cycleEstimates,
                'work_orders' => $cycleWorkOrders,
                'storage'     => $cycleStorage,
                'reefer'      => $cycleReefer,
            ];
        });

        return [
            'container'    => $container,
            'container_no' => $containerNo,
            'cycles'       => $cycles,
            'total_visits' => $gateIns->count(),
        ];
    }

    /**
     * Export search results as CSV rows.
     */
    public function exportCsv(array $filters): \Generator
    {
        yield ['Container No', 'Customer', 'Job No', 'Job Type', 'Gate In', 'Gate Out', 'Job Status', 'Condition', 'Size', 'Cargo Status'];

        GateMovement::with(['yardJob.jobType', 'customer'])
            ->where('movement_type', 'in')
            ->when(!empty($filters['container_no']), fn ($q) => $q->where('container_no', 'LIKE', strtoupper(trim($filters['container_no'])) . '%'))
            ->when(!empty($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(!empty($filters['job_type_code']), fn ($q) => $q->where('job_type_code', $filters['job_type_code']))
            ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('gate_in_time', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),   fn ($q) => $q->whereDate('gate_in_time', '<=', $filters['date_to']))
            ->orderBy('gate_in_time', 'desc')
            ->chunk(200, function ($movements) {
                foreach ($movements as $m) {
                    yield [
                        $m->container_no,
                        optional($m->customer)->name ?? '-',
                        optional($m->yardJob)->job_no ?? '-',
                        optional(optional($m->yardJob)->jobType)->job_type_name ?? $m->job_type_code ?? '-',
                        $m->gate_in_time ? $m->gate_in_time->format('Y-m-d H:i') : '-',
                        '-',
                        optional($m->yardJob)->status ?? '-',
                        $m->condition ?? '-',
                        $m->size ?? '-',
                        $m->cargo_status ?? '-',
                    ];
                }
            });
    }

    /**
     * Pair each gate-in movement to its gate-out using a two-tier strategy:
     *
     *   1. yard_job_id match — records created through the current workflow
     *      have the same yard_job_id on both the gate-in and gate-out rows.
     *
     *   2. Chronological proximity — older records that pre-date the
     *      yard_job_id column (or records entered without a job) are matched
     *      by finding the first unused gate-out whose gate_out_time falls
     *      within [gate_in_time, next_gate_in_time).  Gate-ins are processed
     *      oldest-first so the greedy assignment is temporally correct.
     *
     * @param  Collection  $gateIns   gate-in GateMovement records (any order)
     * @param  Collection  $gateOuts  gate-out GateMovement records for the
     *                                same container(s), sorted gate_out_time ASC
     * @return array<int, GateMovement>  keyed by gate_in.id
     */
    private function buildGateOutMap(Collection $gateIns, Collection $gateOuts): array
    {
        $map     = [];
        $usedIds = [];

        // Gate-outs linked by yard_job_id
        $byJobId = $gateOuts
            ->filter(fn ($go) => !is_null($go->yard_job_id))
            ->keyBy('yard_job_id');

        // Gate-outs without a yard_job_id (older / orphan records), sorted ASC
        $orphans = $gateOuts
            ->filter(fn ($go) => is_null($go->yard_job_id))
            ->sortBy('gate_out_time')
            ->values();

        // Process gate-ins oldest-first so the greedy temporal matching is correct
        $sorted = $gateIns->sortBy('gate_in_time')->values();

        foreach ($sorted as $i => $gi) {
            if (!is_null($gi->yard_job_id) && $byJobId->has($gi->yard_job_id)) {
                // Modern record: match by shared yard_job_id
                $go = $byJobId->get($gi->yard_job_id);
                $map[$gi->id] = $go;
                $usedIds[$go->id] = true;
            } else {
                // Older record: find the first unused orphan gate-out that
                // falls after this gate-in but before the next gate-in
                $from  = $gi->gate_in_time?->timestamp ?? 0;
                $until = $sorted->get($i + 1)?->gate_in_time?->timestamp ?? PHP_INT_MAX;

                foreach ($orphans as $go) {
                    if (isset($usedIds[$go->id])) {
                        continue;
                    }
                    $ts = $go->gate_out_time?->timestamp ?? 0;
                    if ($ts >= $from && $ts < $until) {
                        $map[$gi->id] = $go;
                        $usedIds[$go->id] = true;
                        break;
                    }
                }
            }
        }

        return $map;
    }

    private function withinCycle(mixed $date, mixed $from, mixed $until): bool
    {
        if (!$date || !$from) {
            return false;
        }
        $ts      = is_string($date)  ? strtotime($date)  : $date->timestamp;
        $fromTs  = is_string($from)  ? strtotime($from)  : $from->timestamp;
        $untilTs = $until
            ? (is_string($until) ? strtotime($until) : $until->timestamp)
            : PHP_INT_MAX;

        return $ts >= $fromTs && $ts <= $untilTs;
    }
}
