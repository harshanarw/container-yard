<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:audit-log.view');
    }

    public function index(Request $request)
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        // Reference search (container_no, job_no, invoice_no, etc.)
        if ($ref = trim($request->get('reference', ''))) {
            $query->where('reference', 'like', '%' . $ref . '%');
        }

        // Module filter
        if ($module = $request->get('module')) {
            $query->where('log_name', $module);
        }

        // Event type filter
        if ($event = $request->get('event')) {
            $query->where('event', $event);
        }

        // User filter
        if ($causerId = $request->get('causer_id')) {
            $query->where('causer_id', $causerId);
        }

        // Date range
        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Data for filter dropdowns
        $modules = collect(config('modules', []))
            ->map(fn($m, $key) => ['key' => $key, 'label' => $m['label']])
            ->values();

        $events = [
            'created', 'updated', 'deleted',
            'gate-in', 'gate-out',
            'plug-in', 'plug-out', 'temp-log',
            'approved', 'rejected',
        ];

        $users = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name']);

        return view('audit-log.index', compact('logs', 'modules', 'events', 'users'));
    }

    /** Return the full properties JSON for a single log entry (AJAX). */
    public function detail(AuditLog $auditLog)
    {
        $this->authorize('audit-log.view');

        return response()->json([
            'id'          => $auditLog->id,
            'description' => $auditLog->description,
            'properties'  => $auditLog->properties,
        ]);
    }
}
