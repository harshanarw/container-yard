<?php

use App\Models\Container;
use App\Models\GateMovement;
use App\Services\ContainerMrStatusService;
use Illuminate\Database\Migrations\Migration;

/**
 * Populate the M&R status projection for everything already in the yard.
 *
 * Two passes, because the two projections cover different ground:
 *
 *   1. Every gate-in row, history included — Container Inquiry lists closed
 *      cycles, and each must show what that visit ended as.
 *   2. Every container — its current cycle, plus export readiness.
 *
 * Historical rows with incomplete chains are fine: the resolver's last rung is
 * "awaiting disposition", so a missing history yields a vague-but-true status
 * rather than a confident wrong one.
 *
 * This is a projection, not a source of truth. If the resolver changes later, a
 * fresh migration run and a live database can disagree — and it does not
 * matter, because containers:reconcile-mr-status recomputes daily and repairs
 * exactly that kind of drift.
 *
 * On a large yard prefer running the migration and then
 * `php artisan containers:reconcile-mr-status --fix` out of hours; the work is
 * identical and the command reports progress.
 */
return new class extends Migration
{
    /** Matches ReconcileStorageCommand's batch size. */
    private const CHUNK = 200;

    public function up(): void
    {
        $svc = app(ContainerMrStatusService::class);

        // 1) Every cycle, open and closed.
        GateMovement::where('movement_type', 'in')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($gateIns) use ($svc) {
                $svc->refreshGateIns($gateIns);
            });

        // 2) Every container's current state.
        Container::orderBy('id')
            ->chunkById(self::CHUNK, function ($containers) use ($svc) {
                foreach ($containers as $container) {
                    $svc->refresh($container);
                }
            });
    }

    public function down(): void
    {
        // The columns themselves are dropped by the two migrations above; there
        // is nothing to undo in the data.
    }
};
