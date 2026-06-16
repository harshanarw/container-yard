<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequestObserver extends AuditObserver
{
    protected function getModule(): string            { return 'approvals'; }
    protected function getReference(Model $m): ?string
    {
        // Pull a reference from the polymorphic approvable model
        try {
            $subject = $m->approvable;
            if (!$subject) return null;
            return $subject->estimate_no
                ?? $subject->wo_no
                ?? $subject->invoice_no
                ?? $subject->container_no
                ?? $subject->job_no
                ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref       = $this->getReference($m);
        $newStatus = $diff['new']['status'] ?? null;

        $eventMap = [
            'approved'   => 'approved',
            'rejected'   => 'rejected',
            'cancelled'  => 'deleted',
        ];

        $event   = $eventMap[$newStatus] ?? 'updated';
        $changed = implode(', ', array_keys($diff['old'] ?? []));
        $desc    = match ($newStatus) {
            'approved'  => "Approval request approved" . ($ref ? " — {$ref}" : ''),
            'rejected'  => "Approval request rejected" . ($ref ? " — {$ref}" : ''),
            'cancelled' => "Approval request cancelled" . ($ref ? " — {$ref}" : ''),
            default     => "Approval request updated [{$changed}]" . ($ref ? " — {$ref}" : ''),
        };

        AuditService::log(event: $event, module: $this->getModule(),
            description: $desc, reference: $ref, subject: $m, properties: $diff);
    }
}
