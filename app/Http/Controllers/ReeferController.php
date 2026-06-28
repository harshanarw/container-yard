<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ReeferPlugSession;
use App\Services\NotificationService;
use App\Models\ReeferTempLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReeferController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:yard.reefer.view')->only(['index', 'show']);
        $this->middleware('can:yard.reefer.plug-in')->only(['plugIn', 'storePlugIn']);
        $this->middleware('can:yard.reefer.plug-out')->only(['plugOut', 'storePlugOut']);
        $this->middleware('can:yard.reefer.temp-log')->only(['storeTempLog', 'destroyTempLog']);
    }

    // ── Operations dashboard ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $sessions = ReeferPlugSession::with(['container.equipmentType', 'customer', 'gateMovement', 'createdBy'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->search, function ($q, $s) {
                $q->whereHas('container', fn ($cq) => $cq->where('container_no', 'like', "%{$s}%"));
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'pending'   => ReeferPlugSession::where('status', 'pending')->count(),
            'active'    => ReeferPlugSession::where('status', 'active')->count(),
            'completed' => ReeferPlugSession::where('status', 'completed')->count(),
            'billed'    => ReeferPlugSession::where('status', 'billed')->count(),
        ];

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('yard.reefer.index', compact('sessions', 'stats', 'customers'));
    }

    // ── Plug-In ──────────────────────────────────────────────────────────────

    public function plugIn(ReeferPlugSession $plugSession)
    {
        if (!$plugSession->isPending()) {
            return back()->with('error', 'Session is not in pending status.');
        }

        $session = $plugSession;
        return view('yard.reefer.plug-in', compact('session'));
    }

    public function storePlugIn(Request $request, ReeferPlugSession $plugSession)
    {
        if (!$plugSession->isPending()) {
            return back()->with('error', 'Session is not in pending status.');
        }

        $data = $request->validate([
            'plug_in_at'      => 'required|date',
            'service_type'    => 'required|in:pti,long_term',
            'set_temperature' => 'nullable|numeric|min:-50|max:40',
            'notes'           => 'nullable|string',
        ]);

        $plugSession->update([
            'plug_in_at'      => $data['plug_in_at'],
            'service_type'    => $data['service_type'],
            'set_temperature' => $data['set_temperature'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'status'          => 'active',
            'updated_by'      => Auth::id(),
        ]);

        NotificationService::notifyAll(
            'Reefer Plug-In — ' . $plugSession->container->container_no,
            ($plugSession->customer->name ?? 'Unknown') . ' · Set temp: ' . ($data['set_temperature'] ?? '—') . '°C',
            'info',
            route('yard.reefer.show', $plugSession)
        );

        return redirect()->route('yard.reefer.index')
            ->with('success', "Plug-in recorded for {$plugSession->container->container_no}.");
    }

    // ── Plug-Out ─────────────────────────────────────────────────────────────

    public function plugOut(ReeferPlugSession $plugSession)
    {
        if (!$plugSession->isActive()) {
            return back()->with('error', 'Session is not currently active.');
        }

        $session = $plugSession;
        return view('yard.reefer.plug-out', compact('session'));
    }

    public function storePlugOut(Request $request, ReeferPlugSession $plugSession)
    {
        if (!$plugSession->isActive()) {
            return back()->with('error', 'Session is not currently active.');
        }

        $minDate = $plugSession->plug_in_at
            ? $plugSession->plug_in_at->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');

        $data = $request->validate([
            'plug_out_at' => ['required', 'date', "after_or_equal:{$minDate}"],
            'notes'       => 'nullable|string',
        ]);

        $notes = trim(($plugSession->notes ?? '') . "\n" . ($data['notes'] ?? ''));

        $plugSession->update([
            'plug_out_at' => $data['plug_out_at'],
            'notes'       => $notes ?: null,
            'status'      => 'completed',
            'updated_by'  => Auth::id(),
        ]);

        NotificationService::notifyAll(
            'Reefer Plug-Out — ' . $plugSession->container->container_no,
            ($plugSession->customer->name ?? 'Unknown') . ' · Session complete — ready for billing',
            'info',
            route('yard.reefer.show', $plugSession)
        );

        return redirect()->route('yard.reefer.index')
            ->with('success', "Plug-out recorded for {$plugSession->container->container_no}. Session is now ready for billing.");
    }

    // ── Session detail ────────────────────────────────────────────────────────

    public function show(ReeferPlugSession $plugSession)
    {
        $plugSession->load(['container.equipmentType', 'customer', 'tempLogs.loggedBy', 'createdBy', 'updatedBy']);
        $session = $plugSession;
        return view('yard.reefer.show', compact('session'));
    }

    // ── Temperature Log ───────────────────────────────────────────────────────

    public function storeTempLog(Request $request, ReeferPlugSession $plugSession)
    {
        $data = $request->validate([
            'logged_at'          => 'required|date',
            'set_temperature'    => 'nullable|numeric|min:-50|max:40',
            'return_temperature' => 'nullable|numeric|min:-50|max:40',
            'supply_temperature' => 'nullable|numeric|min:-50|max:40',
            'humidity_pct'       => 'nullable|numeric|min:0|max:100',
            'notes'              => 'nullable|string',
        ]);

        $data['plug_session_id'] = $plugSession->id;
        $data['logged_by']       = Auth::id();

        ReeferTempLog::create($data);

        return back()->with('success', 'Temperature log entry added.');
    }

    public function destroyTempLog(ReeferPlugSession $plugSession, ReeferTempLog $tempLog)
    {
        $tempLog->delete();
        return back()->with('success', 'Temperature log entry removed.');
    }
}
