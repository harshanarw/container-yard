<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\CargoTransfer;
use App\Models\Container;
use App\Support\ContainerNumbers;
use App\Models\GateMovement;
use App\Support\VisitPairing;
use Illuminate\Console\Command;

/**
 * Departures whose cargo status disagrees with their arrival. Report only.
 *
 * Gate-out used to take Laden / Empty from the container master, which gate-in
 * writes once and the movement edit screen never reaches. So a gate-in
 * corrected from Empty to Laden on the edit screen still left as Empty, and
 * the CODECO sent from that departure told the line the box went out empty.
 * Gate-out now reads the visit (`ContainerVisitState`), so this stops
 * happening; this command finds the departures it already happened to.
 *
 * Each row is a visit: the arrival, the departure that closed it (paired the
 * way every other screen pairs them, `VisitPairing`), whether a CODECO has
 * gone out for the departure, and the cargo-status edits on the audit trail.
 *
 * **There is no `--fix`.** Which end is wrong is a judgement about a box that
 * has left the yard: the arrival may be the mistake, not the departure. The
 * yard corrects each one on the movement edit screen and, where a CODECO was
 * sent, re-sends it.
 *
 * Not reported, because laden in / empty out is what they are:
 *   - a box whose cargo was transferred out (`CARGO_RENTAL_OUT` departure);
 *   - a box that held transferred cargo during the visit (the substitute).
 *
 *   php artisan movements:cargo-mismatch
 *   php artisan movements:cargo-mismatch --since=2026-10-01
 *   php artisan movements:cargo-mismatch --container=FFAU6736115
 */
class CargoStatusMismatchCommand extends Command
{
    protected $signature = 'movements:cargo-mismatch
                            {--since= : only departures on or after this date (Y-m-d)}
                            {--container= : limit to a single container number}';

    protected $description = 'Report departures whose Laden / Empty disagrees with their arrival (report only).';

    public function handle(): int
    {
        $since = $this->option('since');
        $only  = $this->option('container');

        $containerIds = GateMovement::where('movement_type', 'out')
            ->whereNotNull('container_id')
            ->when($since, fn ($q) => $q->where('gate_out_time', '>=', $since))
            ->when($only, fn ($q) => ContainerNumbers::whereBelongsTo($q, ContainerNumbers::find($only) ?? []))
            ->distinct()
            ->pluck('container_id');

        $rows = [];

        foreach ($containerIds->chunk(200) as $chunk) {
            $movements = GateMovement::whereIn('container_id', $chunk)->get()->groupBy('container_id');

            $transfers = CargoTransfer::whereIn('substitute_container_id', $chunk)
                ->where('status', '!=', 'cancelled')
                ->get()
                ->groupBy('substitute_container_id');

            foreach ($movements as $containerId => $perContainer) {
                $ins  = $perContainer->where('movement_type', 'in')->values();
                $outs = $perContainer->where('movement_type', 'out')->values();

                foreach (VisitPairing::pair($ins, $outs) as $inId => $out) {
                    $in = $ins->firstWhere('id', $inId);

                    if (! $this->mismatched($in, $out, $transfers->get($containerId))) {
                        continue;
                    }
                    if ($since && $out->gate_out_time && $out->gate_out_time->lt($since)) {
                        continue;
                    }

                    $rows[] = [
                        $out->container_no,
                        trim(($in->eir_no ?? '#' . $in->id) . ' ' . $in->gate_in_time?->format('d M Y')),
                        ucfirst((string) $in->cargo_status),
                        trim(($out->eir_no ?? '#' . $out->id) . ' ' . $out->gate_out_time?->format('d M Y')),
                        ucfirst((string) $out->cargo_status),
                        $out->codeco_exported_at ? 'sent ' . $out->codeco_exported_at->format('d M Y') : 'not sent',
                        $this->cargoEdits([$in->id, $out->id]),
                    ];
                }
            }
        }

        if (! $rows) {
            $this->info('No departure disagrees with its arrival on Laden / Empty.');

            return self::SUCCESS;
        }

        $this->table(
            ['Container', 'Arrival', 'In', 'Departure', 'Out', 'CODECO', 'Cargo-status edits'],
            $rows,
        );

        $this->line(count($rows) . ' visit(s) left with a different Laden / Empty from the one they arrived with.');
        $this->line('Report only. Correct the wrong end on Yard → Gate movement → Edit, and re-send the CODECO where it was sent.');

        return self::SUCCESS;
    }

    private function mismatched(?GateMovement $in, GateMovement $out, $transfers): bool
    {
        if (! $in) {
            return false;
        }

        $inStatus  = $in->cargo_status;
        $outStatus = $out->cargo_status;

        if (! in_array($inStatus, ['laden', 'empty'], true) || ! in_array($outStatus, ['laden', 'empty'], true)) {
            return false;
        }
        if ($inStatus === $outStatus) {
            return false;
        }

        // Cargo moved out of this box by a transfer: laden in, empty out.
        if ($out->gate_out_purpose === 'CARGO_RENTAL_OUT') {
            return false;
        }

        // Cargo moved into this box during the visit: empty in, laden (or
        // emptied again) out.
        $inDate  = $in->gate_in_time?->toDateString();
        $outDate = $out->gate_out_time?->toDateString();
        $heldCargo = collect($transfers)->contains(fn ($t) => (! $inDate || $t->transfer_date->toDateString() >= $inDate)
            && (! $outDate || $t->transfer_date->toDateString() <= $outDate));

        return ! $heldCargo;
    }

    /** The cargo-status edits on either end, from the audit trail. */
    private function cargoEdits(array $movementIds): string
    {
        return AuditLog::where('subject_type', GateMovement::class)
            ->whereIn('subject_id', $movementIds)
            ->where('event', 'updated')
            ->orderBy('created_at')
            ->get()
            ->filter(fn ($log) => array_key_exists('cargo_status', $log->properties['old'] ?? []))
            ->map(fn ($log) => sprintf(
                '%s %s→%s by %s',
                $log->created_at?->format('d M'),
                $log->properties['old']['cargo_status'] ?? '?',
                $log->properties['new']['cargo_status'] ?? '?',
                $log->causer_name ?? 'unknown',
            ))
            ->implode('; ') ?: '-';
    }
}
