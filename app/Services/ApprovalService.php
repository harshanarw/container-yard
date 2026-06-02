<?php

namespace App\Services;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    /**
     * Create a new approval request and auto-approve any steps flagged auto_approve_on_create.
     */
    public function initiate(Model $approvable, string $workflowType, User $initiator, ?string $ipAddress = null): ApprovalRequest
    {
        $steps = ApprovalWorkflow::stepsFor($workflowType);

        if ($steps->isEmpty()) {
            throw new \RuntimeException("No active workflow steps found for type [{$workflowType}].");
        }

        return DB::transaction(function () use ($approvable, $workflowType, $steps, $initiator, $ipAddress) {
            $request = ApprovalRequest::create([
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id'   => $approvable->getKey(),
                'workflow_type'   => $workflowType,
                'status'          => 'pending',
                'initiated_by'    => $initiator->id,
                'initiated_at'    => now(),
            ]);

            foreach ($steps as $step) {
                $isAutoApprove = $step->auto_approve_on_create;

                $action = ApprovalAction::create([
                    'approval_request_id' => $request->id,
                    'step_order'          => $step->step_order,
                    'step_key'            => $step->step_key,
                    'step_label'          => $step->step_label,
                    'status'              => $isAutoApprove ? 'approved' : 'pending',
                    'actioned_by'         => $isAutoApprove ? $initiator->id : null,
                    'actioned_at'         => $isAutoApprove ? now() : null,
                    'remarks'             => $isAutoApprove ? 'Auto-approved on creation' : null,
                    'ip_address'          => $isAutoApprove ? $ipAddress : null,
                ]);
            }

            $this->recalculateRequestStatus($request);

            return $request->fresh(['actions']);
        });
    }

    /**
     * Approve the current pending step for the given user.
     */
    public function approve(ApprovalRequest $request, User $actor, ?string $remarks = null, ?string $ipAddress = null): ApprovalAction
    {
        $action = $this->resolveActionForActor($request, $actor);

        DB::transaction(function () use ($request, $action, $actor, $remarks, $ipAddress) {
            $action->update([
                'status'      => 'approved',
                'actioned_by' => $actor->id,
                'actioned_at' => now(),
                'remarks'     => $remarks,
                'ip_address'  => $ipAddress,
            ]);

            $this->recalculateRequestStatus($request);
        });

        return $action->fresh();
    }

    /**
     * Reject the current pending step for the given user.
     */
    public function reject(ApprovalRequest $request, User $actor, ?string $remarks = null, ?string $ipAddress = null): ApprovalAction
    {
        $action = $this->resolveActionForActor($request, $actor);

        DB::transaction(function () use ($request, $action, $actor, $remarks, $ipAddress) {
            $action->update([
                'status'      => 'rejected',
                'actioned_by' => $actor->id,
                'actioned_at' => now(),
                'remarks'     => $remarks,
                'ip_address'  => $ipAddress,
            ]);

            $request->update([
                'status'       => 'rejected',
                'completed_at' => now(),
            ]);
        });

        return $action->fresh();
    }

    /**
     * Cancel an active approval request (admin / initiator only).
     */
    public function cancel(ApprovalRequest $request, User $actor, string $reason): void
    {
        if (!$this->canCancel($request, $actor)) {
            throw new \RuntimeException('You do not have permission to cancel this approval request.');
        }

        $request->update([
            'status'              => 'cancelled',
            'cancelled_by'        => $actor->id,
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Whether the given user can action the next pending step.
     */
    public function canAction(ApprovalRequest $request, User $actor): bool
    {
        if (!$request->isPending()) {
            return false;
        }

        $action = $request->nextPendingAction();
        if (!$action) {
            return false;
        }

        $step = ApprovalWorkflow::where('document_type', $request->workflow_type)
            ->where('step_key', $action->step_key)
            ->first();

        if (!$step || !$step->is_active) {
            return false;
        }

        if ($step->required_role === null) {
            return true;
        }

        return in_array($actor->role, [$step->required_role, 'system_administrator', 'administrator'], true);
    }

    /**
     * Get all pending actions the given user can act on across all requests.
     */
    public function getPendingActionsForUser(User $actor): \Illuminate\Database\Eloquent\Collection
    {
        $query = ApprovalAction::query()
            ->where('status', 'pending')
            ->whereHas('approvalRequest', fn($q) => $q->where('status', 'pending'))
            ->with(['approvalRequest.approvable', 'approvalRequest.initiatedBy'])
            ->orderBy('created_at');

        if (!in_array($actor->role, ['system_administrator', 'administrator'], true)) {
            $query->whereHas('approvalRequest', function ($q) use ($actor) {
                $q->whereIn('workflow_type', $this->workflowTypesForRole($actor->role));
            })->where(function ($q) use ($actor) {
                // Ensure it is the next in sequence for that request
                $q->whereRaw('step_order = (
                    SELECT MIN(a2.step_order)
                    FROM approval_actions a2
                    WHERE a2.approval_request_id = approval_actions.approval_request_id
                      AND a2.status = ?
                )', ['pending']);
            });
        }

        return $query->get()->filter(fn($action) => $this->canAction($action->approvalRequest, $actor));
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function resolveActionForActor(ApprovalRequest $request, User $actor): ApprovalAction
    {
        if (!$request->isPending()) {
            throw new \RuntimeException('This approval request is no longer pending.');
        }

        $action = $request->nextPendingAction();

        if (!$action) {
            throw new \RuntimeException('No pending step found for this request.');
        }

        if (!$this->canAction($request, $actor)) {
            throw new \RuntimeException('You are not authorised to action this approval step.');
        }

        return $action;
    }

    private function recalculateRequestStatus(ApprovalRequest $request): void
    {
        $request->load('actions');

        $anyRejected  = $request->actions->contains(fn($a) => $a->status === 'rejected');
        $allApproved  = $request->actions->every(fn($a) => $a->status === 'approved');

        if ($anyRejected) {
            $request->update(['status' => 'rejected', 'completed_at' => now()]);
        } elseif ($allApproved) {
            $request->update(['status' => 'approved', 'completed_at' => now()]);
        }
    }

    private function canCancel(ApprovalRequest $request, User $actor): bool
    {
        if (in_array($actor->role, ['system_administrator', 'administrator'], true)) {
            return true;
        }

        return $request->initiated_by === $actor->id && $request->isPending();
    }

    private function workflowTypesForRole(string $role): array
    {
        $steps = ApprovalWorkflow::where('required_role', $role)->where('is_active', true)->get();
        return $steps->pluck('document_type')->unique()->values()->all();
    }
}
