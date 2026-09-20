<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\GateMovement;
use App\Services\ContainerMrStatusService;
use Illuminate\Console\Command;

/**
 * Empty reefers recorded as running, which is usually the gate form's fault.
 *
 * A PTI tests refrigeration that is about to be used. An empty reefer with the
 * machinery off is a Non-Operating Reefer and needs none — the gate-in
 * controller has defaulted an empty one to NOR from the start.
 *
 * The form did not. It hard-coded `operating` as the checked radio whatever the
 * cargo status was, so the field was never absent and the server's own default
 * never ran; only the browser corrected it, and any path that did not fire that
 * sync recorded an empty box as running. Those containers then sit on the M&R
 * board reading "PTI due", asking for an inspection of machinery nobody is
 * going to switch on.
 *
 * **Reports rather than repairs, by default.** An empty reefer genuinely can be
 * running — a feeder movement, pre-cooling before a stuffing — and those are
 * indistinguishable in the data from the ones the form got wrong. Only the yard
 * knows which is which, so a blanket rewrite would stamp a decision nobody
 * made. `--fix` is there for when they have looked.
 *
 *   php artisan reefer:empty-operating
 *   php artisan reefer:empty-operating --fix
 *   php artisan reefer:empty-operating --fix --container=RFER0000001
 */
class EmptyOperatingReefersCommand extends Command
{
    protected $signature = 'reefer:empty-operating
                            {--fix : record these as non-operating and refresh the M&R status}
                            {--container=* : limit to specific container numbers}';

    protected $description = 'Empty reefers recorded as operating, which makes them demand a PTI they do not need';

    public function handle(ContainerMrStatusService $mr): int
    {
        $movements = GateMovement::query()
            ->where('movement_type', 'in')
            ->where('cargo_status', 'empty')
            ->where('reefer_mode', 'operating')
            ->when($this->option('container'), fn ($q, $nos) => $q->whereIn('container_no', $nos))
            ->with('container')
            // Only reefers. `reefer_mode` is null on a dry box, so the filter
            // above has already excluded them, but a container whose equipment
            // type changed later would slip through.
            ->get()
            ->filter(fn ($m) => $m->container?->isReefer())
            ->values();

        if ($movements->isEmpty()) {
            $this->info('No empty reefers are recorded as operating.');

            return self::SUCCESS;
        }

        $this->table(
            ['Container', 'Gated In', 'Job Type', 'M&R Status', 'Movement'],
            $movements->map(fn ($m) => [
                $m->container_no,
                $m->gate_in_time?->format('d M Y H:i') ?? '-',
                $m->job_type_code ?? '-',
                $m->container?->mr_status ?? '-',
                $m->eir_no ?? $m->id,
            ])->all(),
        );

        $this->newLine();
        $this->warn($movements->count() . ' empty reefer(s) recorded as operating.');
        $this->line('An empty reefer can legitimately be running — a feeder movement, or pre-cooling.');
        $this->line('Check these against the paperwork before rewriting them.');

        if (! $this->option('fix')) {
            $this->newLine();
            $this->line('Re-run with --fix to record them as non-operating.');

            return self::SUCCESS;
        }

        $fixed = 0;

        foreach ($movements as $movement) {
            // Query builder, not the model: this is a historical correction,
            // not a gate event, and firing the observers would re-derive the
            // status against today rather than against the visit. It is
            // refreshed explicitly below instead.
            GateMovement::where('id', $movement->id)->update(['reefer_mode' => 'non_operating']);

            if ($movement->container) {
                $mr->refresh(Container::find($movement->container_id));
            }

            $fixed++;
        }

        $this->newLine();
        $this->info("Recorded {$fixed} movement(s) as non-operating and refreshed their M&R status.");

        return self::SUCCESS;
    }
}
