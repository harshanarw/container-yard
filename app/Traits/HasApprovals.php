<?php

namespace App\Traits;

use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasApprovals
{
    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable')->latestOfMany('id');
    }

    public function allApprovalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function isApproved(): bool
    {
        return $this->approvalRequest?->isApproved() ?? false;
    }

    public function isPendingApproval(): bool
    {
        return $this->approvalRequest?->isPending() ?? false;
    }

    public function isRejected(): bool
    {
        return $this->approvalRequest?->isRejected() ?? false;
    }

    public function hasAnyApprovalRequest(): bool
    {
        return $this->allApprovalRequests()->exists();
    }
}
