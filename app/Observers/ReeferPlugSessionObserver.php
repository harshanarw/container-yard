<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class ReeferPlugSessionObserver extends AuditObserver
{
    protected function getModule(): string            { return 'yard.reefer'; }
    protected function getReference(Model $m): ?string
    {
        return $m->container?->container_no ?? null;
    }

    public function created(Model $m): void
    {
        $ref = $this->getReference($m);
        AuditService::log(
            event: 'plug-in',
            module: $this->getModule(),
            description: 'Reefer plug-in recorded' . ($ref ? " — {$ref}" : '') . ($m->plug_in_at ? ' at ' . $m->plug_in_at->format('d M Y H:i') : ''),
            reference: $ref,
            subject: $m,
            properties: AuditService::snapshot($m),
        );
    }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref = $this->getReference($m);

        // Detect plug-out: plug_out_at was null, now set
        if (isset($diff['new']['plug_out_at']) && $diff['old']['plug_out_at'] === null) {
            AuditService::log(
                event: 'plug-out',
                module: $this->getModule(),
                description: 'Reefer plug-out recorded' . ($ref ? " — {$ref}" : '') . ($m->plug_out_at ? ' at ' . $m->plug_out_at->format('d M Y H:i') : ''),
                reference: $ref,
                subject: $m,
                properties: $diff,
            );
            return;
        }

        AuditService::log(
            event: 'updated',
            module: $this->getModule(),
            description: 'Reefer session updated' . ($ref ? " — {$ref}" : ''),
            reference: $ref,
            subject: $m,
            properties: $diff,
        );
    }
}
