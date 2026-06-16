<?php

namespace App\Observers;

use App\Models\GateMovement;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class GateMovementObserver extends AuditObserver
{
    protected function getModule(): string   { return 'yard'; }
    protected function getReference(Model $m): ?string { return $m->container_no ?? null; }

    private function typeLabel(Model $m): string
    {
        return $m->movement_type === 'out' ? 'Gate-Out' : 'Gate-In';
    }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return $this->typeLabel($m) . ' recorded'
            . ($ref ? " — {$ref}" : '')
            . ($m->vehicle_plate ? " · truck {$m->vehicle_plate}" : '');
    }

    protected function describeUpdated(Model $m, ?string $ref, array $diff): string
    {
        $changed = implode(', ', array_keys($diff['old'] ?? []));
        return $this->typeLabel($m) . " #{$m->id} updated [{$changed}]"
            . ($ref ? " — {$ref}" : '');
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return $this->typeLabel($m) . " #{$m->id} deleted"
            . ($ref ? " — {$ref}" : '');
    }

    // Gate-In / Gate-Out created events get a specific event type for easy filtering
    public function created(Model $m): void
    {
        $ref   = $this->getReference($m);
        $event = $m->movement_type === 'out' ? 'gate-out' : 'gate-in';
        AuditService::log(
            event: $event,
            module: $this->getModule(),
            description: $this->describeCreated($m, $ref),
            reference: $ref,
            subject: $m,
            properties: AuditService::snapshot($m),
        );
    }
}
