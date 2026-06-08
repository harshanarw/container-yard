<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuardCaptureRequest;
use App\Models\CompanySetting;
use App\Models\GateMovement;
use App\Models\GuardCapture;
use App\Services\ContainerOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GuardPostController extends Controller
{
    public function __construct(private ContainerOcrService $ocr) {}

    // ── Guard Post Dashboard ──────────────────────────────────────────────────

    public function index(): View
    {
        $this->requireFeature();

        $recent = GuardCapture::where('captured_by', auth()->id())
            ->orderByDesc('captured_at')
            ->limit(20)
            ->get();

        return view('guard-post.index', compact('recent'));
    }

    // ── New Capture ───────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $this->requireFeature();

        $direction = in_array($request->query('direction'), ['gate_in', 'gate_out'])
            ? $request->query('direction')
            : 'gate_in';

        return view('guard-post.create', compact('direction'));
    }

    public function store(StoreGuardCaptureRequest $request): RedirectResponse
    {
        $this->requireFeature();

        $data = [
            'direction'        => $request->direction,
            'status'           => 'pending',
            'reference_no'     => GuardCapture::generateReference(),
            'container_number' => strtoupper(trim($request->container_number ?? '')),
            'iso_code'         => strtoupper(trim($request->iso_code ?? '')),
            'vehicle_number'   => strtoupper(trim($request->vehicle_number ?? '')),
            'vehicle_type'     => $request->vehicle_type,
            'driver_name'      => $request->driver_name,
            'nic_number'       => strtoupper(trim($request->nic_number ?? '')),
            'driver_phone'     => $request->driver_phone,
            'captured_by'      => auth()->id(),
            'captured_at'      => now(),
        ];

        // Store images
        foreach ([
            'container_image' => 'container_image_path',
            'plate_image'     => 'plate_image_path',
            'nic_front'       => 'nic_front_path',
            'nic_back'        => 'nic_back_path',
            'license_front'   => 'license_front_path',
        ] as $field => $column) {
            if ($request->hasFile($field)) {
                $data[$column] = $request->file($field)->store('guard-captures', 'public');
            }
        }

        // Auto-run OCR on container image
        if ($request->hasFile('container_image') && empty($data['container_number'])) {
            try {
                $result = $this->ocr->extractFromImage($request->file('container_image'));
                if ($result['container_no']) {
                    $data['ocr_container_no'] = $result['container_no'];
                    $data['container_number'] = $result['container_no'];
                    if ($result['iso_type'] && empty($data['iso_code'])) {
                        $data['iso_code'] = $result['iso_type'];
                    }
                }
            } catch (\Throwable) {
                // OCR failure is non-fatal
            }
        }

        $capture = GuardCapture::create($data);

        return redirect()->route('guard-post.status', $capture)
            ->with('success', 'Capture submitted. Reference: ' . $capture->reference_no);
    }

    // ── Status Screen ─────────────────────────────────────────────────────────

    public function status(GuardCapture $capture): View
    {
        $this->requireFeature();

        return view('guard-post.status', compact('capture'));
    }

    // Polling endpoint — returns current status as JSON
    public function statusJson(GuardCapture $capture): JsonResponse
    {
        $this->requireFeature();

        return response()->json([
            'status'         => $capture->status,
            'status_label'   => $capture->status_label,
            'badge_class'    => $capture->status_badge_class,
            'clearance_note' => $capture->clearance_note,
            'cleared_by'     => $capture->clearedBy?->full_name,
            'cleared_at'     => $capture->cleared_at?->format('h:i A'),
        ]);
    }

    // ── OCR Scan (AJAX) ───────────────────────────────────────────────────────

    public function ocrScan(Request $request): JsonResponse
    {
        $this->requireFeature();

        $request->validate(['image' => ['required', 'file', 'image', 'max:10240']]);

        $result = $this->ocr->extractFromImage($request->file('image'));

        return response()->json([
            'success'      => $result['container_no'] !== null,
            'container_no' => $result['container_no'],
            'iso_type'     => $result['iso_type'],
            'message'      => $result['container_no']
                ? 'Container number detected: ' . $result['container_no']
                : 'Could not read container number. You can enter it manually.',
        ]);
    }

    // ── Operations Queue ──────────────────────────────────────────────────────

    public function queue(Request $request): View
    {
        $this->requireFeature();
        $this->requireOpsRole();

        $status = $request->query('status', 'pending');

        $captures = GuardCapture::with(['capturedBy', 'clearedBy', 'linkedGateMovement'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('captured_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'pending'  => GuardCapture::where('status', 'pending')->count(),
            'cleared'  => GuardCapture::where('status', 'cleared')->count(),
            'hold'     => GuardCapture::where('status', 'hold')->count(),
            'rejected' => GuardCapture::where('status', 'rejected')->count(),
        ];

        return view('guard-post.queue', compact('captures', 'status', 'counts'));
    }

    // ── Update Status ─────────────────────────────────────────────────────────

    public function updateStatus(Request $request, GuardCapture $capture): RedirectResponse
    {
        $this->requireFeature();
        $this->requireOpsRole();

        $request->validate([
            'action' => ['required', 'in:cleared,hold,rejected'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $capture->update([
            'status'         => $request->action,
            'clearance_note' => $request->note,
            'cleared_by'     => auth()->id(),
            'cleared_at'     => now(),
        ]);

        $label = match($request->action) {
            'cleared'  => 'Cleared',
            'hold'     => 'Placed on hold',
            'rejected' => 'Rejected',
        };

        return back()->with('success', "Capture {$capture->reference_no} — {$label}.");
    }

    // ── Link to Gate Movement ─────────────────────────────────────────────────

    public function link(Request $request, GuardCapture $capture): RedirectResponse
    {
        $this->requireFeature();
        $this->requireOpsRole();

        $request->validate([
            'gate_movement_id' => ['required', 'exists:gate_movements,id'],
        ]);

        $capture->update(['linked_gate_movement_id' => $request->gate_movement_id]);

        return back()->with('success', "Capture {$capture->reference_no} linked to gate movement.");
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function requireFeature(): void
    {
        if (!CompanySetting::current()->enable_guard_post) {
            abort(404);
        }
    }

    private function requireOpsRole(): void
    {
        $user = auth()->user();
        if (!in_array($user->role, ['gate_officer', 'yard_supervisor', 'administrator', 'system_administrator'])) {
            abort(403);
        }
    }
}
