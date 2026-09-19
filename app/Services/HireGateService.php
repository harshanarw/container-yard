<?php

namespace App\Services;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\LessorOnHire;
use App\Support\HireGateState;

/**
 * Assembles {@see HireGateState} for a container — the queries the gate needs
 * to know what its hire agreements mean, run once, in one place.
 *
 * One method feeds both the gate form (`containerLookup`, which warns while the
 * truck is still at the barrier) and the gate save (`gateOut`, which decides).
 * Written as one method for the same reason `pendingPlugSession()` is: when the
 * warning and the decision are separate implementations of the same rule, they
 * drift, and the operator is told one thing on screen and another on submit.
 */
class HireGateService
{
    public function forContainer(Container $container): HireGateState
    {
        // The yard's lease of this box from its line. Independent of the
        // letting: a lease can run with the box sitting on the ground and no
        // renter at all, which is the state between two lettings.
        $lease = LessorOnHire::with(['yardJob.customer', 'lessor'])
            ->where('container_id', $container->id)
            ->where('status', 'active')
            ->latest('on_hire_date')
            ->first();

        // The letting currently out with a customer. Keyed on the container, so
        // it is found from the container number alone — which is all a gate
        // officer has when the truck arrives.
        $letting = ContainerHire::with(['yardJob.customer', 'hireCustomer'])
            ->where('container_id', $container->id)
            ->where('status', 'active')
            ->latest('on_hire_date')
            ->first();

        return new HireGateState(
            lease:      $lease,
            letting:    $letting,
            renter:     $letting?->hireCustomer,
            lettingJob: $letting?->yardJob,
            stayJob:    app(ContainerCustodyService::class)->visitJob($container),
        );
    }
}
