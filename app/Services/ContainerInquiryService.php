<?php

namespace App\Services;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\ReeferPlugSession;
use App\Models\WorkOrder;
use App\Models\YardJob;
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
        return GateMovement::with(['yardJob.jobType', 'yardJob.gateOut', 'customer', 'createdBy'])
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
     * Build the full history for a container number: profile + job cycles.
     */
    public function getContainerHistory(string $containerNo): array
    {
        $containerNo = strtoupper(trim($containerNo));

        $container = Container::where('container_no', $containerNo)
            ->with(['customer', 'equipmentType', 'grade'])
            ->first();

        // Gate-in movements ordered newest first
        $gateIns = GateMovement::with(['yardJob.jobType', 'customer', 'grade', 'createdBy'])
            ->where('container_no', $containerNo)
            ->where('movement_type', 'in')
            ->orderBy('gate_in_time', 'desc')
            ->get();

        // All gate-out movements indexed by yard_job_id for quick lookup
        $gateOuts = GateMovement::where('container_no', $containerNo)
            ->where('movement_type', 'out')
            ->orderBy('gate_out_time', 'desc')
            ->get()
            ->keyBy('yard_job_id');

        // All surveys indexed by container_no
        $inquiries = Inquiry::where('container_no', $containerNo)
            ->orderBy('created_at', 'desc')
            ->get();

        // All estimates indexed by inquiry_id
        $estimates = Estimate::where('container_no', $containerNo)
            ->with(['createdBy'])
            ->orderBy('estimate_date', 'desc')
            ->get();

        // All work orders
        $workOrders = WorkOrder::where('container_no', $containerNo)
            ->with(['createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Storage records
        $storageRecords = YardStorage::where('container_id', optional($container)->id)
            ->orderBy('gate_in_date', 'desc')
            ->get();

        // Reefer sessions
        $reeferSessions = ReeferPlugSession::where('container_id', optional($container)->id)
            ->orderBy('plug_in_at', 'desc')
            ->get();

        // Build job cycles
        $cycles = $gateIns->map(function (GateMovement $gateIn) use (
            $gateOuts, $inquiries, $estimates, $workOrders, $storageRecords, $reeferSessions
        ) {
            $jobId    = $gateIn->yard_job_id;
            $yardJob  = $gateIn->yardJob;
            $gateOut  = $jobId ? ($gateOuts->get($jobId)) : null;

            // Match surveys to this job cycle by yard_job_id (via gate movement survey_id) or date proximity
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
                'gate_in'      => $gateIn,
                'gate_out'     => $gateOut,
                'yard_job'     => $yardJob,
                'inquiries'    => $cycleInquiries,
                'estimates'    => $cycleEstimates,
                'work_orders'  => $cycleWorkOrders,
                'storage'      => $cycleStorage,
                'reefer'       => $cycleReefer,
            ];
        });

        return [
            'container'     => $container,
            'container_no'  => $containerNo,
            'cycles'        => $cycles,
            'total_visits'  => $gateIns->count(),
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

    private function withinCycle(mixed $date, mixed $from, mixed $until): bool
    {
        if (!$date || !$from) {
            return false;
        }
        $ts     = is_string($date) ? strtotime($date) : $date->timestamp;
        $fromTs = is_string($from) ? strtotime($from) : $from->timestamp;
        $untilTs = $until ? (is_string($until) ? strtotime($until) : $until->timestamp) : PHP_INT_MAX;

        return $ts >= $fromTs && $ts <= $untilTs;
    }
}
