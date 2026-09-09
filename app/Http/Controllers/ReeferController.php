<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ReeferPlugSession;
use App\Services\AuditService;
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
        $this->middleware('can:yard.reefer.amend')->only(['amend', 'storeAmend']);
    }

    // ── Operations dashboard ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $sessions = ReeferPlugSession::with(['container.equipmentType', 'customer', 'gateMovement', 'createdBy', 'yardJob.jobType'])
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
            // Sessions with days still to invoice — which since periodic billing
            // includes containers *still on power*, not only finished ones.
            // Their electricity is owed for every day that passes, and counting
            // only completed sessions hid exactly the revenue this number is
            // read for. `billed` now means fully invoiced, so excluding it (and
            // the never-plugged) is the whole test.
            'completed' => ReeferPlugSession::whereIn('status', ['active', 'completed'])
                ->whereNotNull('plug_in_at')
                ->count(),
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
            'Reefer Plug-In - ' . $plugSession->container->container_no,
            ($plugSession->customer->name ?? 'Unknown') . ' · Set temp: ' . ($data['set_temperature'] ?? '-') . '°C',
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
            'Reefer Plug-Out - ' . $plugSession->container->container_no,
            ($plugSession->customer->name ?? 'Unknown') . ' · Session complete - ready for billing',
            'info',
            route('yard.reefer.show', $plugSession)
        );

        return redirect()->route('yard.reefer.index')
            ->with('success', "Plug-out recorded for {$plugSession->container->container_no}. Session is now ready for billing.");
    }

    // ── Amend recorded plug times ────────────────────────────────────────────

    /**
     * Correcting a plug time after the fact.
     *
     * Recording a plug-in moves a session to `active` and a plug-out moves it
     * to `completed`, and both screens accept only the status before their own
     * — so until this action existed a mis-keyed time could be changed by
     * nothing short of a database edit, with no validation and no audit trail.
     *
     * It also does the one thing that recovers a session closed with no plug-in
     * at all: given both times, a `not_plugged` session becomes `completed`,
     * which is what puts it back in front of the electricity invoice.
     */
    public function amend(ReeferPlugSession $plugSession)
    {
        if ($refusal = $this->amendmentRefusal($plugSession)) {
            return redirect()->route('yard.reefer.show', $plugSession)->with('error', $refusal);
        }

        $plugSession->load(['container', 'customer', 'gateMovement', 'gateOutMovement']);
        $session = $plugSession;

        return view('yard.reefer.amend', compact('session'));
    }

    public function storeAmend(Request $request, ReeferPlugSession $plugSession)
    {
        if ($refusal = $this->amendmentRefusal($plugSession)) {
            return redirect()->route('yard.reefer.show', $plugSession)->with('error', $refusal);
        }

        $plugSession->load(['container', 'gateMovement', 'gateOutMovement']);

        [$from, $to] = array_values($plugSession->visitWindow());

        // An active session has not gone off power yet, so it amends only its
        // plug-in; entering a plug-out here would be recording a plug-out by the
        // back door, which is what the plug-out screen is for.
        $wantsPlugOut = $plugSession->amendsPlugOut();

        // A concrete timestamp rather than the string `now`: the date rules
        // resolve a keyword parameter through strtotime, which reads the system
        // clock and ignores a frozen Carbon, so `now` would be untestable and
        // would drift from what the rest of the app calls "now".
        $notFuture = now()->format('Y-m-d H:i:s');

        $rules = [
            'plug_in_at' => ['required', 'date', 'before_or_equal:' . $notFuture],
            'reason'     => ['required', 'string', 'min:5', 'max:500'],
        ];

        // A reefer cannot be plugged in before it arrives.
        if ($from) {
            $rules['plug_in_at'][] = 'after_or_equal:' . $from->format('Y-m-d H:i:s');
        }

        if ($wantsPlugOut) {
            // Strictly after: equal times bill nothing and almost certainly mean
            // a mis-key rather than a zero-length session.
            $rules['plug_out_at'] = ['required', 'date', 'after:plug_in_at', 'before_or_equal:' . $notFuture];

            // And it cannot draw power after it leaves.
            if ($to) {
                $rules['plug_out_at'][] = 'before_or_equal:' . $to->format('Y-m-d H:i:s');
            }
        }

        $data = $request->validate($rules, [], [
            'plug_in_at'  => 'plug-in time',
            'plug_out_at' => 'plug-out time',
            'reason'      => 'reason for the amendment',
        ]);

        $before = [
            'status'      => $plugSession->status,
            'plug_in_at'  => $plugSession->plug_in_at?->format('d M Y H:i') ?? 'not recorded',
            'plug_out_at' => $plugSession->plug_out_at?->format('d M Y H:i') ?? 'not recorded',
        ];

        $updates = [
            'plug_in_at' => $data['plug_in_at'],
            'updated_by' => Auth::id(),
        ];

        if ($wantsPlugOut) {
            $updates['plug_out_at'] = $data['plug_out_at'];
            // A session closed without a plug-in was parked in `not_plugged`.
            // Now that it has both times it is an ordinary finished session, and
            // `unbilled()` will pick it up for the next electricity invoice.
            $updates['status'] = 'completed';
        }

        $plugSession->update($updates);

        // The observer records the old and new values on its own. This adds the
        // one thing it cannot know: why the number changed.
        AuditService::log(
            event: 'amended',
            module: 'yard.reefer',
            description: sprintf(
                'Reefer plug times amended - %s. Was %s status, plug-in %s, plug-out %s. Reason: %s',
                $plugSession->container?->container_no ?? 'unknown container',
                $before['status'],
                $before['plug_in_at'],
                $before['plug_out_at'],
                $data['reason'],
            ),
            reference: $plugSession->container?->container_no,
            subject: $plugSession,
            properties: ['before' => $before, 'reason' => $data['reason']],
        );

        return redirect()->route('yard.reefer.show', $plugSession)
            ->with('success', $before['status'] === 'not_plugged'
                ? "Plug times recorded for {$plugSession->container?->container_no}. The session is now ready for billing."
                : "Plug times amended for {$plugSession->container?->container_no}.");
    }

    /** Why this session may not be amended, or null if it may. */
    private function amendmentRefusal(ReeferPlugSession $session): ?string
    {
        if ($session->isBilled()) {
            return 'This session has been billed. Cancel the electricity invoice first - that returns the session to completed and it can then be amended.';
        }

        if ($session->isPending()) {
            return 'No plug times have been recorded yet. Use Record Plug-In instead.';
        }

        return $session->isAmendable() ? null : 'This session cannot be amended.';
    }

    // ── Session detail ────────────────────────────────────────────────────────

    public function show(ReeferPlugSession $plugSession)
    {
        $plugSession->load(['container.equipmentType', 'customer', 'tempLogs.loggedBy', 'createdBy', 'updatedBy']);
        $session = $plugSession;

        // Which invoices have charged which days. Since power is billed in
        // instalments, "why is this box only being charged nine days?" is a
        // question the screen has to be able to answer.
        $billing = \App\Models\ReeferElectricityInvoiceLine::with('invoice:id,invoice_no,status,invoice_date')
            ->where('plug_session_id', $plugSession->id)
            ->whereHas('invoice')
            ->get()
            ->sortBy(fn ($l) => $l->billed_from?->toDateString() ?? '')
            ->values();

        return view('yard.reefer.show', compact('session', 'billing'));
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
