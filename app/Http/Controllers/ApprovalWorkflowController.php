<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use Illuminate\Http\Request;

class ApprovalWorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.approval-workflows.view')->only(['index']);
        $this->middleware('can:settings.approval-workflows.create')->only(['store']);
        $this->middleware('can:settings.approval-workflows.edit')->only(['update', 'toggleActive']);
    }

    private const ROLES = [
        ''                    => '— Any authenticated user —',
        'system_administrator'=> 'System Administrator',
        'administrator'       => 'Administrator',
        'yard_supervisor'     => 'Yard Supervisor',
        'gate_officer'        => 'Gate Officer',
        'inspector'           => 'Inspector',
        'billing_clerk'       => 'Billing Clerk',
    ];

    private const DOC_TYPE_LABELS = [
        'gate_pass'    => 'Gate Pass (Outward)',
        'gate_pass_in' => 'Gate Pass (Inward)',
    ];

    private function authorise(): void
    {
        abort_unless(auth()->user()?->isSystemAdmin(), 403, 'System Administrator access required.');
    }

    public function index()
    {
        $this->authorise();

        $steps        = ApprovalWorkflow::orderBy('document_type')->orderBy('step_order')->get()
                            ->groupBy('document_type');
        $roles        = self::ROLES;
        $docTypeLabels = self::DOC_TYPE_LABELS;

        return view('settings.approval-workflows.index', compact('steps', 'roles', 'docTypeLabels'));
    }

    public function store(Request $request)
    {
        $this->authorise();
        $data = $request->validate([
            'document_type'          => 'required|string|max:50',
            'step_label'             => 'required|string|max:100',
            'required_role'          => 'nullable|string|max:60',
            'auto_approve_on_create' => 'boolean',
        ]);

        $docType   = $data['document_type'];
        $maxOrder  = ApprovalWorkflow::where('document_type', $docType)->max('step_order') ?? 0;
        $stepKey   = \Illuminate\Support\Str::slug($data['step_label'], '_') . '_' . ($maxOrder + 1);

        // Ensure step_key uniqueness within document_type
        $base = $stepKey;
        $i    = 2;
        while (ApprovalWorkflow::where('document_type', $docType)->where('step_key', $stepKey)->exists()) {
            $stepKey = $base . '_' . $i++;
        }

        ApprovalWorkflow::create([
            'document_type'          => $docType,
            'step_order'             => $maxOrder + 1,
            'step_key'               => $stepKey,
            'step_label'             => $data['step_label'],
            'required_role'          => $data['required_role'] ?: null,
            'auto_approve_on_create' => $request->boolean('auto_approve_on_create'),
            'is_active'              => true,
        ]);

        return back()->with('success', "Step \"{$data['step_label']}\" added to {$docType} workflow.");
    }

    public function update(Request $request, ApprovalWorkflow $approvalWorkflow)
    {
        $this->authorise();
        $data = $request->validate([
            'step_label'             => 'required|string|max:100',
            'required_role'          => 'nullable|string|max:60',
            'auto_approve_on_create' => 'boolean',
        ]);

        $approvalWorkflow->update([
            'step_label'             => $data['step_label'],
            'required_role'          => $data['required_role'] ?: null,
            'auto_approve_on_create' => $request->boolean('auto_approve_on_create'),
        ]);

        return back()->with('success', "Step \"{$approvalWorkflow->step_label}\" updated.");
    }

    public function toggleActive(ApprovalWorkflow $approvalWorkflow)
    {
        $this->authorise();
        $approvalWorkflow->update(['is_active' => !$approvalWorkflow->is_active]);

        $state = $approvalWorkflow->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Step \"{$approvalWorkflow->step_label}\" {$state}.");
    }
}
