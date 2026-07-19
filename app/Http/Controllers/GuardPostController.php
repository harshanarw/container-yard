<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuardCaptureRequest;
use App\Models\CompanySetting;
use App\Models\GuardCapture;
use App\Services\ContainerOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GuardPostController extends Controller
{
    public function __construct(private ContainerOcrService $ocr)
    {
        $this->middleware('can:guard-post.view')->only(['index', 'status', 'statusJson', 'queue']);
        $this->middleware('can:guard-post.create')->only(['create', 'store', 'ocrScan']);
        $this->middleware('can:guard-post.edit')->only(['updateStatus', 'link']);
    }

    // ─── Guard helpers ────────────────────────────────────────────────────────

    private function requireFeature(): void
    {
        if (!CompanySetting::current()->enable_guard_post) {
            abort(404);
        }
    }

    private function requireOpsRole(): void
    {
        $role = Auth::user()->role;
        if (!in_array($role, ['gate_officer', 'yard_supervisor', 'administrator', 'system_administrator'])) {
            abort(403, 'Operations access required.');
        }
    }

    // ─── Security officer dashboard ───────────────────────────────────────────

    public function index()
    {
        $this->requireFeature();

        $captures = GuardCapture::where('captured_by', Auth::id())
            ->latest()
            ->take(20)
            ->get();

        return view('guard-post.index', compact('captures'));
    }

    // ─── New capture form ─────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $this->requireFeature();

        $direction = $request->get('direction', 'gate_in');
        if (!in_array($direction, ['gate_in', 'gate_out'])) {
            $direction = 'gate_in';
        }

        return view('guard-post.create', compact('direction'));
    }

    // ─── Store capture ────────────────────────────────────────────────────────

    public function store(StoreGuardCaptureRequest $request)
    {
        $this->requireFeature();

        $data = [
            'reference_no' => GuardCapture::generateReference(),
            'direction'    => $request->direction,
            'status'       => 'pending',
            'captured_by'  => Auth::id(),
            'captured_at'  => now(),
        ];

        // Container
        if ($request->hasFile('container_image')) {
            $data['container_image_path'] = $request->file('container_image')
                ->store('guard-captures/containers', 'public');

            // Run OCR whenever there's an image — a manually-typed number no longer
            // suppresses it, so the ISO type code and weights are still captured.
            try {
                $ocr = $this->ocr->extractFromImage($request->file('container_image'));
                if ($ocr['container_no']) {
                    $data['ocr_container_no'] = $ocr['container_no'];
                    if (! $request->filled('container_number')) {
                        $data['container_number'] = $ocr['container_no'];
                    }
                }
                if ($ocr['iso_type'] && ! $request->filled('iso_code')) {
                    $data['iso_code'] = $ocr['iso_type'];
                }
                if (! empty($ocr['tare_kg']))      $data['tare_kg']      = $ocr['tare_kg'];
                if (! empty($ocr['max_gross_kg'])) $data['max_gross_kg'] = $ocr['max_gross_kg'];
            } catch (\Throwable) {
                // OCR failure is non-fatal
            }
        }
        $data['container_number'] ??= $request->container_number;
        $data['iso_code']         ??= $request->iso_code;

        // Resolve the equipment type from the final ISO size/type code (typed or
        // OCR-read), so the capture — and the gate-in hand-off — carry a real type.
        if (! empty($data['iso_code'])) {
            $data['equipment_type_id'] = \App\Models\EquipmentType::where('iso_code', $data['iso_code'])->value('id');
        }

        // Vehicle
        if ($request->hasFile('plate_image')) {
            $data['plate_image_path'] = $request->file('plate_image')
                ->store('guard-captures/plates', 'public');
        }
        $data['vehicle_number'] = $request->vehicle_number;
        $data['vehicle_type']   = $request->vehicle_type;

        // Driver
        foreach (['nic_front', 'nic_back', 'license_front'] as $field) {
            if ($request->hasFile($field)) {
                $data["{$field}_path"] = $request->file($field)
                    ->store('guard-captures/driver-docs', 'public');
            }
        }
        $data['driver_name']  = $request->driver_name;
        $data['nic_number']   = $request->nic_number;
        $data['driver_phone'] = $request->driver_phone;
        $data['notes']        = $request->notes;

        $capture = GuardCapture::create($data);

        // Remember the driver in the master (best-effort — never break the capture).
        try {
            app(\App\Services\DriverService::class)->remember(
                $data['driver_name'] ?? null,
                $data['nic_number'] ?? null,
                $data['driver_phone'] ?? null,
                auth()->id(),
            );
        } catch (\Throwable $e) {
            \Log::warning('[GuardPost] Driver master upsert failed: ' . $e->getMessage());
        }

        $redirect = redirect()->route('guard-post.status', $capture)
            ->with('success', "Capture {$capture->reference_no} submitted. Waiting for clearance.");

        // Soft check-digit warning: the shape is already valid (form rule), but a
        // wrong ISO 6346 check digit usually means an OCR/typo slip — flag it so
        // the ops desk double-checks, without blocking the capture. Computed from
        // the submitted (normalised) number so it doesn't depend on how the model
        // stored/derived container_number.
        $cno = \App\Support\Iso6346::normalize($request->input('container_number', ''));
        if ($cno !== '' && ! \App\Support\Iso6346::checkDigitValid($cno)) {
            $redirect->with('warning', "Container number {$cno} fails the ISO 6346 check digit — please verify it against the box.");
        }

        return $redirect;
    }

    // ─── Status display ───────────────────────────────────────────────────────

    public function status(GuardCapture $capture)
    {
        $this->requireFeature();

        return view('guard-post.status', compact('capture'));
    }

    public function statusJson(GuardCapture $capture): JsonResponse
    {
        $this->requireFeature();

        return response()->json([
            'status'        => $capture->status,
            'status_label'  => $capture->status_label,
            'badge_class'   => $capture->status_badge_class,
            'cleared_at'    => $capture->cleared_at?->format('d M Y H:i'),
            'cleared_by'    => $capture->clearedBy?->full_name,
            'notes'         => $capture->notes,
        ]);
    }

    // ─── OCR scan (AJAX) ──────────────────────────────────────────────────────

    public function ocrScan(Request $request): JsonResponse
    {
        $this->requireFeature();

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'],
        ]);

        // Tell the UI plainly when OCR can't run on this host, so it shows a
        // "not available — enter manually" state rather than "could not read".
        if (! $this->ocr->isAvailable()) {
            return response()->json(['available' => false, 'success' => false]);
        }

        $result = $this->ocr->extractFromImage($request->file('image'));

        $eqt = $result['iso_type']
            ? \App\Models\EquipmentType::where('iso_code', $result['iso_type'])->first()
            : null;

        return response()->json([
            'available'         => true,
            'success'           => $result['container_no'] !== null,
            'container_no'      => $result['container_no'],
            'iso_type'          => $result['iso_type'],
            'check_digit_valid' => $result['check_digit_valid'],
            'tare_kg'           => $result['tare_kg'],
            'max_gross_kg'      => $result['max_gross_kg'],
            'equipment'         => $eqt ? [
                'code'      => $eqt->eqt_code,
                'size'      => $eqt->size,
                'type_code' => $eqt->type_code,
            ] : null,
        ]);
    }

    // ─── Ops desk queue ───────────────────────────────────────────────────────

    public function queue(Request $request)
    {
        $this->requireFeature();
        $this->requireOpsRole();

        $filter = $request->get('status', 'pending');
        $allowed = ['pending', 'cleared', 'hold', 'rejected', 'all'];
        if (!in_array($filter, $allowed)) {
            $filter = 'pending';
        }

        $query = GuardCapture::with(['capturedBy', 'clearedBy'])->latest();
        if ($filter !== 'all') {
            $query->where('status', $filter);
        }
        $captures = $query->paginate(25)->withQueryString();

        $counts = GuardCapture::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('guard-post.queue', compact('captures', 'filter', 'counts'));
    }

    // ─── Update status (clear / hold / reject) ────────────────────────────────

    public function updateStatus(Request $request, GuardCapture $capture)
    {
        $this->requireFeature();
        $this->requireOpsRole();

        $request->validate([
            'action' => ['required', 'in:cleared,hold,rejected'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $update = [
            'status'     => $request->action,
            'notes'      => $request->notes,
            'cleared_by' => Auth::id(),
        ];
        if ($request->action === 'cleared') {
            $update['cleared_at'] = now();
        }

        $capture->update($update);

        return back()->with('success', "Capture {$capture->reference_no} marked as {$request->action}.");
    }

    // ─── Link to gate movement ────────────────────────────────────────────────

    public function link(Request $request, GuardCapture $capture)
    {
        $this->requireFeature();
        $this->requireOpsRole();

        $request->validate([
            'gate_movement_id' => ['required', 'exists:gate_movements,id'],
        ]);

        $capture->update(['linked_gate_movement_id' => $request->gate_movement_id]);

        return back()->with('success', "Capture linked to gate movement #{$request->gate_movement_id}.");
    }
}
