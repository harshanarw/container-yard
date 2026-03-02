<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
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
                                        ->whereDate('created_at', today())
                                        ->count(),
            'gate_out_today'    => GateMovement::where('movement_type', 'out')
                                        ->whereDate('created_at', today())
                                        ->count(),
            'pending_estimates' => Estimate::where('status', 'draft')->count(),
        ];

        $recentGateMovements = GateMovement::with(['container', 'customer'])
            ->latest()
            ->take(10)
            ->get();

        $recentInquiries = Inquiry::with(['container', 'customer'])
            ->latest()
            ->take(5)
            ->get();

        $yardOccupancyByRow = YardLocation::selectRaw('row, status, count(*) as total')
            ->groupBy('row', 'status')
            ->get()
            ->groupBy('row');

        return view('dashboard.index', compact(
            'stats',
            'recentGateMovements',
            'recentInquiries',
            'yardOccupancyByRow'
        ));
    }
}
