<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\StorageZone;
use App\Models\YardLocation;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_containers'  => Container::whereIn('status', ['in_yard', 'in_repair', 'reserved'])->count(),
            'available_slots'   => YardLocation::where('status', 'empty')->count(),
            'total_capacity'    => YardLocation::count(),
            'pending_repairs'   => Container::where('status', 'in_repair')->count(),
            'open_inquiries'    => Inquiry::whereIn('status', ['open', 'in_progress'])->count(),
            'customers'         => Customer::where('status', 'active')->count(),
            'gate_in_today'     => GateMovement::where('movement_type', 'in')
                                        ->whereDate('created_at', today())->count(),
            'gate_out_today'    => GateMovement::where('movement_type', 'out')
                                        ->whereDate('created_at', today())->count(),
            'gate_in_week'      => GateMovement::where('movement_type', 'in')
                                        ->whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
            'pending_estimates' => Estimate::where('status', 'draft')->count(),
            'unallocated'       => Container::where('status', 'in_yard')->whereNull('location_row')->count(),
        ];

        $recentGateMovements = GateMovement::with(['customer'])
            ->latest()
            ->take(8)
            ->get();

        $recentInquiries = Inquiry::with(['customer'])
            ->whereIn('status', ['open', 'in_progress'])
            ->latest()
            ->take(5)
            ->get();

        $pendingEstimates = Estimate::with(['customer'])
            ->where('status', 'draft')
            ->latest()
            ->take(5)
            ->get();

        $zones = StorageZone::withCount([
            'yardLocations',
            'yardLocations as empty_count'    => fn($q) => $q->where('status', 'empty'),
            'yardLocations as occupied_count' => fn($q) => $q->where('status', 'occupied'),
            'yardLocations as reserved_count' => fn($q) => $q->where('status', 'reserved'),
        ])->orderBy('sort_order')->get();

        return view('dashboard.index', compact(
            'stats',
            'recentGateMovements',
            'recentInquiries',
            'pendingEstimates',
            'zones'
        ));
    }
}
