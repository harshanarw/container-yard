<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class ReeferTempLogObserver extends AuditObserver
{
    protected function getModule(): string            { return 'yard.reefer'; }
    protected function getReference(Model $m): ?string
    {
        return $m->plugSession?->container?->container_no ?? null;
    }

    public function created(Model $m): void
    {
        $ref = $this->getReference($m);
        AuditService::log(
            event: 'temp-log',
            module: $this->getModule(),
            description: 'Temperature log recorded'
                . ($m->supply_temperature !== null ? " — supply {$m->supply_temperature}°C" : '')
                . ($ref ? " · {$ref}" : ''),
            reference: $ref,
            subject: $m,
            properties: AuditService::snapshot($m),
        );
    }
}
