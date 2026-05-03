<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function inventory(Request $request)
    {
        $containers = Container::with('customer')
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->status,      fn ($q, $v) => $q->where('status', $v))
            ->when($request->size,        fn ($q, $v) => $q->where('size', $v))
            ->when($request->condition,   fn ($q, $v) => $q->where('condition', $v))
            ->when($request->date_from,   fn ($q, $v) => $q->whereDate('gate_in_date', '>=', $v))
            ->when($request->date_to,     fn ($q, $v) => $q->whereDate('gate_in_date', '<=', $v))
            ->orderBy('gate_in_date', 'desc')
            ->get();

        $summary = [
            'total'        => $containers->count(),
            'in_yard'      => $containers->where('status', 'in_yard')->count(),
            'in_repair'    => $containers->where('status', 'in_repair')->count(),
            'released'     => $containers->where('status', 'released')->count(),
            'by_size_20'   => $containers->where('size', '20')->count(),
            'by_size_40'   => $containers->where('size', '40')->count(),
            'by_size_45'   => $containers->where('size', '45')->count(),
        ];

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('reports.inventory', compact('containers', 'summary', 'customers'));
    }

    public function billing(Request $request)
    {
        $storageRecords = YardStorage::with(['container', 'customer'])
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->date_from,   fn ($q, $v) => $q->whereDate('gate_in_date', '>=', $v))
            ->when($request->date_to,     fn ($q, $v) => $q->whereDate('gate_out_date', '<=', $v))
            ->whereNotNull('gate_out_date')
            ->orderBy('gate_out_date', 'desc')
            ->get();

        $summary = [
            'total_records'    => $storageRecords->count(),
            'total_revenue'    => $storageRecords->sum('total_charge'),
            'total_days'       => $storageRecords->sum('total_days'),
            'avg_stay'         => $storageRecords->avg('total_days'),
        ];

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('reports.billing', compact('storageRecords', 'summary', 'customers'));
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

        // Build CSV
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="daily-movements-' . now()->format('Ymd-His') . '.csv"',
        ];

        $callback = function () use ($movements) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Batch Ref', 'Movement Type', 'Container No', 'Size', 'Equipment Type',
                'Container Operator', 'Condition', 'Cargo Status', 'Seal No',
                'Vehicle Plate', 'Driver Name', 'Driver IC', 'Release Order',
                'Gate In Date/Time', 'Gate Out Date/Time',
                'Location Row', 'Location Bay', 'Location Tier',
                'Remarks', 'Recorded By',
            ]);
            foreach ($movements as $m) {
                fputcsv($out, [
                    $m->csv_batch_ref,
                    strtoupper($m->movement_type),
                    $m->container_no,
                    $m->size,
                    $m->container_type,
                    $m->customer->name ?? '—',
                    $m->condition,
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
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
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
                    $cargoCode = strtoupper($m->cargo_status) === 'FULL' ? '1' : '4'; // 1=full, 4=empty
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
