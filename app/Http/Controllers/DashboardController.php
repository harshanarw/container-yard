<?php

namespace App\Http\Controllers;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\StorageZone;
use App\Models\YardLocation;
use App\Support\MrStatusCatalogue;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        // Security officers go directly to Guard Post when the feature is enabled
        $user = Auth::user();
        if ($user->isSecurityOfficer() && CompanySetting::current()->enable_guard_post) {
            return redirect()->route('guard-post.index');
        }

        $stats = [
            'total_containers'  => Container::whereIn('status', Container::IN_YARD_STATUSES)->count(),
            'available_empties' => Container::where('status', 'available')->count(),
            'available_slots'   => YardLocation::where('status', 'empty')->count(),
            'total_capacity'    => YardLocation::count(),
            // Kept for the existing tile; the roll-up below is what actually
            // answers "what is the yard waiting on".
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
            'unallocated'       => Container::whereIn('status', ['in_yard', 'available'])->whereNull('location_row')->count(),
        ];

        // M&R roll-up — one grouped query over the projection, for containers
        // physically in the yard.
        //
        // 'pending_repairs' above counts the in_repair disposition, which says a
        // work order is open but not what it is waiting on. Twelve boxes stuck
        // awaiting QC and twelve mid-repair are the same number there and very
        // different problems.
        $mrCounts = Container::whereIn('status', Container::IN_YARD_STATUSES)
            ->whereNotNull('mr_status_group')
            ->groupBy('mr_status_group')
            ->selectRaw('mr_status_group, COUNT(*) as total')
            ->pluck('total', 'mr_status_group');

        $mrRollup = collect(MrStatusCatalogue::groups())
            ->map(fn ($label, $key) => [
                'label' => $label,
                'count' => (int) ($mrCounts[$key] ?? 0),
                'badge' => MrStatusCatalogue::badgeClass(
                    // Any code in the group carries the group's colour.
                    collect(MrStatusCatalogue::CATALOGUE)
                        ->search(fn ($meta) => $meta[1] === $key) ?: MrStatusCatalogue::AWAITING_DISPOSITION
                ),
            ])
            // Closed statuses describe boxes that have left; they are not part
            // of "what is in the yard right now".
            ->except([MrStatusCatalogue::GROUP_CLOSED]);

        $exportReadyCount = Container::available()->exportReady()->count();

        $approvalEnabled = CompanySetting::current()->enable_digital_approvals ?? false;

        $approvalStats = $approvalEnabled ? [
            'total_pending'   => ApprovalRequest::where('status', 'pending')->count(),
            'approved_today'  => ApprovalRequest::where('status', 'approved')
                                    ->whereDate('completed_at', today())->count(),
            'rejected_today'  => ApprovalRequest::where('status', 'rejected')
                                    ->whereDate('completed_at', today())->count(),
            'my_pending'      => ApprovalAction::where('status', 'pending')
                                    ->whereHas('approvalRequest', fn($q) => $q->where('status', 'pending'))
                                    ->count(),
        ] : null;

        $recentApprovals = $approvalEnabled
            ? ApprovalRequest::with(['approvable', 'initiatedBy'])
                ->whereIn('status', ['approved', 'rejected', 'pending'])
                ->latest('updated_at')
                ->take(5)
                ->get()
            : collect();

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

        $storageUsage = app(\App\Services\StorageUsageService::class)->summary();

        return view('dashboard.index', compact(
            'stats',
            'recentGateMovements',
            'recentInquiries',
            'pendingEstimates',
            'zones',
            'approvalEnabled',
            'approvalStats',
            'recentApprovals',
            'storageUsage',
            'mrRollup',
            'exportReadyCount'
        ));
    }
}
