<?php

namespace App\Http\Controllers;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\GateMovement;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvalService) {}

    public function pending()
    {
        $user    = Auth::user();
        $actions = $this->approvalService->getPendingActionsForUser($user);

        return view('approvals.pending', compact('actions'));
    }

    public function initiateGatePass(Request $request, GateMovement $movement)
    {
        if ($movement->movement_type !== 'out') {
            abort(404);
        }

        if ($movement->allApprovalRequests()->whereIn('status', ['pending', 'approved'])->exists()) {
            return back()->with('error', 'An approval request already exists for this gate pass.');
        }

        try {
            $this->approvalService->initiate($movement, 'gate_pass', Auth::user(), $request->ip());
            return back()->with('success', 'Approval request submitted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, ApprovalAction $action)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);

        try {
            $this->approvalService->approve(
                $action->approvalRequest,
                Auth::user(),
                $request->input('remarks'),
                $request->ip()
            );
            return back()->with('success', 'Step approved successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, ApprovalAction $action)
    {
        $request->validate(['remarks' => 'required|string|max:500']);

        try {
            $this->approvalService->reject(
                $action->approvalRequest,
                Auth::user(),
                $request->input('remarks'),
                $request->ip()
            );
            return back()->with('success', 'Step rejected.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, ApprovalRequest $approvalRequest)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->approvalService->cancel($approvalRequest, Auth::user(), $request->input('reason'));
            return back()->with('success', 'Approval request cancelled.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
