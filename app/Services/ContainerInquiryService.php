<?php

namespace App\Services;

use App\Models\Container;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\ReeferPlugSession;
use App\Models\WorkOrder;
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
            // Basic filters
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
            // Advanced filters
            ->when(!empty($filters['vessel_name']), fn ($q) => $q->where('vessel_name', 'LIKE', '%' . trim($filters['vessel_name']) . '%'))
            ->when(!empty($filters['voyage_no']),   fn ($q) => $q->where('voyage_no',   'LIKE', '%' . trim($filters['voyage_no'])   . '%'))
            ->when(!empty($filters['bl_number']),   fn ($q) => $q->where('bl_number',   'LIKE', '%' . trim($filters['bl_number'])   . '%'))
            ->when(!empty($filters['seal_no']),     fn ($q) => $q->where('seal_no',     'LIKE', '%' . trim($filters['seal_no'])     . '%'))
            ->when(!empty($filters['eir_ref']),     fn ($q) => $q->where('id', (int) $filters['eir_ref']))
            ->orderBy('gate_in_time', 'desc')
            ->paginate($perPage);
    }

    /**
     * Build gate-out map for a page of search results (index view).
     *
     * One batch query for all gate-outs on the page; pairs by yard_job_id
     * first, then chronological proximity for older orphan records.
     *
     * @return array<int, GateMovement>  keyed by gate_in.id
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
     * Build the full history for a container number.
     *
     * Returns:
     *   container, container_no, cycles, total_visits,
     *   stats, financials, timeline
     */
    public function getContainerHistory(string $containerNo): array
    {
        $containerNo = strtoupper(trim($containerNo));

        $container = Container::where('container_no', $containerNo)
            ->with(['customer', 'equipmentType', 'grade'])
            ->first();

        $gateIns = GateMovement::with(['yardJob.jobType', 'customer', 'grade', 'createdBy'])
            ->where('container_no', $containerNo)
            ->where('movement_type', 'in')
            ->orderBy('gate_in_time', 'desc')
            ->get();

        $allGateOuts = GateMovement::with(['customer', 'createdBy'])
            ->where('container_no', $containerNo)
            ->where('movement_type', 'out')
            ->orderBy('gate_out_time', 'asc')
            ->get();

        $gateOutMap = $this->buildGateOutMap($gateIns, $allGateOuts);

        $inquiries = Inquiry::where('container_no', $containerNo)
            ->orderBy('created_at', 'desc')
            ->get();

        $estimates = Estimate::where('container_no', $containerNo)
            ->with(['createdBy'])
            ->orderBy('estimate_date', 'desc')
            ->get();

        $workOrders = WorkOrder::where('container_no', $containerNo)
            ->with(['createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $storageRecords = YardStorage::where('container_id', optional($container)->id)
            ->orderBy('gate_in_date', 'desc')
            ->get();

        $reeferSessions = ReeferPlugSession::where('container_id', optional($container)->id)
            ->orderBy('plug_in_at', 'desc')
            ->get();

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

        // ── Stats ─────────────────────────────────────────────────────────────
        $cycleDays = $cycles->map(function ($cycle) {
            $gi = $cycle['gate_in'];
            $go = $cycle['gate_out'];
            if (!$gi->gate_in_time) {
                return 0;
            }
            return (int) $gi->gate_in_time->diffInDays($go?->gate_out_time ?? now());
        });

        $stats = [
            'total_visits'      => $gateIns->count(),
            'total_days'        => $cycleDays->sum(),
            'avg_days'          => $gateIns->count() > 0 ? (int) round($cycleDays->avg()) : 0,
            'longest_stay_days' => $cycleDays->max() ?? 0,
        ];

        // ── Financials ────────────────────────────────────────────────────────
        $estimatesByStatus = $estimates->groupBy('status')->map(fn ($g) => [
            'count' => $g->count(),
            'total' => (float) $g->sum('grand_total'),
        ]);

        $financials = [
            'storage_total'        => (float) $storageRecords->sum('total_charge'),
            'estimates_by_status'  => $estimatesByStatus,
            'approved_estimate'    => (float) ($estimatesByStatus->get('approved')['total'] ?? 0),
            'work_order_counts'    => $workOrders->groupBy('status')->map->count(),
            'total_work_orders'    => $workOrders->count(),
        ];

        // ── Timeline ──────────────────────────────────────────────────────────
        $timeline = $this->buildTimeline($cycles);

        return [
            'container'    => $container,
            'container_no' => $containerNo,
            'cycles'       => $cycles,
            'total_visits' => $gateIns->count(),
            'stats'        => $stats,
            'financials'   => $financials,
            'timeline'     => $timeline,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Pair each gate-in movement to its gate-out.
     *
     * Tier 1: yard_job_id match (modern records).
     * Tier 2: chronological proximity for older records without yard_job_id.
     *         Gate-ins are processed oldest-first so the greedy assignment
     *         stays temporally correct.
     *
     * @return array<int, GateMovement>  keyed by gate_in.id
     */
    private function buildGateOutMap(Collection $gateIns, Collection $gateOuts): array
    {
        $map     = [];
        $usedIds = [];

        $byJobId = $gateOuts
            ->filter(fn ($go) => !is_null($go->yard_job_id))
            ->keyBy('yard_job_id');

        $orphans = $gateOuts
            ->filter(fn ($go) => is_null($go->yard_job_id))
            ->sortBy('gate_out_time')
            ->values();

        $sorted = $gateIns->sortBy('gate_in_time')->values();

        foreach ($sorted as $i => $gi) {
            if (!is_null($gi->yard_job_id) && $byJobId->has($gi->yard_job_id)) {
                $go = $byJobId->get($gi->yard_job_id);
                $map[$gi->id]     = $go;
                $usedIds[$go->id] = true;
            } else {
                $from  = $gi->gate_in_time?->timestamp ?? 0;
                $until = $sorted->get($i + 1)?->gate_in_time?->timestamp ?? PHP_INT_MAX;

                foreach ($orphans as $go) {
                    if (isset($usedIds[$go->id])) {
                        continue;
                    }
                    $ts = $go->gate_out_time?->timestamp ?? 0;
                    if ($ts >= $from && $ts < $until) {
                        $map[$gi->id]     = $go;
                        $usedIds[$go->id] = true;
                        break;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Build a flat, chronologically sorted timeline of all events across
     * all cycles for a container.  Newest events appear first.
     *
     * @return array<int, array>
     */
    private function buildTimeline(Collection $cycles): array
    {
        $events   = [];
        $total    = $cycles->count();

        foreach ($cycles as $idx => $cycle) {
            $gateIn  = $cycle['gate_in'];
            $gateOut = $cycle['gate_out'];
            $yardJob = $cycle['yard_job'];
            $visitNo = $total - $idx; // #1 = oldest, #N = newest

            if ($gateIn->gate_in_time) {
                $events[] = [
                    'ts'      => $gateIn->gate_in_time,
                    'type'    => 'gate_in',
                    'icon'    => 'bi-box-arrow-in-right',
                    'color'   => 'success',
                    'title'   => 'Gate In',
                    'sub'     => $yardJob
                        ? 'Job ' . $yardJob->job_no . ($yardJob->jobType ? ' · ' . $yardJob->jobType->type_short_code : '')
                        : null,
                    'meta'    => implode(' · ', array_filter([
                        optional($gateIn->customer)->name,
                        $gateIn->condition ? ucfirst(str_replace('_', ' ', $gateIn->condition)) : null,
                        $gateIn->size ? $gateIn->size . 'ft' : null,
                    ])),
                    'badge'   => null,
                    'visit'   => $visitNo,
                    'eir_ref' => $gateIn->id,
                ];
            }

            foreach ($cycle['inquiries'] as $inq) {
                $events[] = [
                    'ts'    => $inq->created_at,
                    'type'  => 'survey',
                    'icon'  => 'bi-clipboard-check',
                    'color' => 'warning',
                    'title' => 'Survey — ' . ($inq->inquiry_no ?? ('INQ-' . $inq->id)),
                    'sub'   => $inq->survey_type ? ucfirst(str_replace('_', ' ', $inq->survey_type)) : null,
                    'meta'  => null,
                    'badge' => $inq->status ? ucfirst($inq->status) : null,
                    'visit' => $visitNo,
                ];
            }

            foreach ($cycle['estimates'] as $est) {
                $amount = $est->grand_total ? number_format((float) $est->grand_total, 2) : null;
                $events[] = [
                    'ts'    => $est->estimate_date ?? $est->created_at,
                    'type'  => 'estimate',
                    'icon'  => 'bi-calculator',
                    'color' => 'info',
                    'title' => 'Estimate — ' . $est->estimate_no,
                    'sub'   => $amount ? ($est->currency ? $est->currency . ' ' . $amount : $amount) : null,
                    'meta'  => null,
                    'badge' => $est->status ? ucfirst($est->status) : null,
                    'visit' => $visitNo,
                ];
            }

            foreach ($cycle['work_orders'] as $wo) {
                $events[] = [
                    'ts'    => $wo->created_at,
                    'type'  => 'work_order',
                    'icon'  => 'bi-tools',
                    'color' => 'primary',
                    'title' => 'Work Order — ' . $wo->wo_no,
                    'sub'   => null,
                    'meta'  => null,
                    'badge' => $wo->status ? ucfirst(str_replace('_', ' ', $wo->status)) : null,
                    'visit' => $visitNo,
                ];
            }

            foreach ($cycle['storage'] as $sr) {
                if ($sr->gate_in_date) {
                    $events[] = [
                        'ts'    => $sr->gate_in_date,
                        'type'  => 'storage',
                        'icon'  => 'bi-archive',
                        'color' => 'secondary',
                        'title' => 'Storage Period',
                        'sub'   => $sr->gate_out_date
                            ? 'Until ' . $sr->gate_out_date->format('d M Y') . ' · ' . ($sr->total_days ?? '?') . ' days'
                            : 'Ongoing',
                        'meta'  => $sr->total_charge ? number_format((float) $sr->total_charge, 2) : null,
                        'badge' => $sr->billing_status ? ucfirst($sr->billing_status) : null,
                        'visit' => $visitNo,
                    ];
                }
            }

            foreach ($cycle['reefer'] as $rs) {
                if ($rs->plug_in_at) {
                    $events[] = [
                        'ts'    => $rs->plug_in_at,
                        'type'  => 'reefer',
                        'icon'  => 'bi-thermometer-snow',
                        'color' => 'info',
                        'title' => 'Reefer Plug In',
                        'sub'   => $rs->set_temp_c !== null ? 'Set temp: ' . $rs->set_temp_c . '°C' : null,
                        'meta'  => null,
                        'badge' => $rs->status ?? null,
                        'visit' => $visitNo,
                    ];
                }
            }

            if ($gateOut?->gate_out_time) {
                $events[] = [
                    'ts'    => $gateOut->gate_out_time,
                    'type'  => 'gate_out',
                    'icon'  => 'bi-box-arrow-right',
                    'color' => 'danger',
                    'title' => 'Gate Out',
                    'sub'   => $yardJob ? 'Job ' . $yardJob->job_no . ' closed' : null,
                    'meta'  => implode(' · ', array_filter([
                        $gateOut->vehicle_plate,
                        $gateOut->driver_name,
                    ])),
                    'badge' => null,
                    'visit' => $visitNo,
                ];
            }
        }

        // Sort newest first
        usort($events, fn ($a, $b) => $b['ts']->timestamp <=> $a['ts']->timestamp);

        return $events;
    }

    private function withinCycle(mixed $date, mixed $from, mixed $until): bool
    {
        if (!$date || !$from) {
            return false;
        }
        $ts      = is_string($date) ? strtotime($date) : $date->timestamp;
        $fromTs  = is_string($from) ? strtotime($from) : $from->timestamp;
        $untilTs = $until
            ? (is_string($until) ? strtotime($until) : $until->timestamp)
            : PHP_INT_MAX;

        return $ts >= $fromTs && $ts <= $untilTs;
    }
}
