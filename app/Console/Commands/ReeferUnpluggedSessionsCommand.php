<?php

namespace App\Console\Commands;

use App\Models\ReeferPlugSession;
use Illuminate\Console\Command;

/**
 * Reefer plug sessions that closed without a plug-in ever being recorded.
 *
 * Each one is a reefer that sat in the yard and produced no electricity charge.
 * Two very different things look identical in the data, and only the yard can
 * tell them apart:
 *
 *   - The box was never plugged in — a reefer moving dry cargo, say. Nothing is
 *     owed, and `--mark-not-plugged` records that.
 *   - The box *was* plugged in and nobody used the plug-in screen. Then the
 *     electricity is owed and unbilled, and the times need entering before the
 *     session can be charged.
 *
 * The stay length is the tell. A laden reefer does not sit thirty-five days
 * without power and keep its cargo, so a long stay almost certainly means the
 * second case. That is why this reports rather than repairs: a blanket
 * re-label would stamp unbilled revenue with a word that reads like a decision
 * somebody made.
 *
 *   php artisan reefer:unplugged-sessions
 *   php artisan reefer:unplugged-sessions --mark-not-plugged --id=16 --id=19
 */
class ReeferUnpluggedSessionsCommand extends Command
{
    protected $signature = 'reefer:unplugged-sessions
                            {--mark-not-plugged : record that these boxes were never plugged in}
                            {--id=* : limit to specific session ids}';

    protected $description = 'Reefer sessions that closed with no plug-in recorded, and so were never billed';

    public function handle(): int
    {
        $sessions = ReeferPlugSession::with(['container', 'customer', 'gateMovement', 'gateOutMovement'])
            ->where('status', 'completed')
            ->whereNull('plug_in_at')
            ->when($this->option('id'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('No completed sessions are missing a plug-in.');

            return self::SUCCESS;
        }

        $totalDays = 0;
        $rows      = [];

        foreach ($sessions as $session) {
            // The stay, not the plug time — the plug time is what is missing.
            $in   = $session->gateMovement?->gate_in_time;
            $out  = $session->gateOutMovement?->gate_out_time;
            $days = $in && $out ? $in->diffInDays($out) : null;
            $totalDays += (int) $days;

            $rows[] = [
                $session->id,
                $session->container?->container_no ?? '-',
                $session->service_type,
                $session->customer?->name ?? '-',
                $in?->format('d M Y H:i') ?? '-',
                $out?->format('d M Y H:i') ?? '-',
                $days === null ? '-' : $days . 'd',
            ];
        }

        $this->table(['ID', 'Container', 'Type', 'Customer', 'Gate In', 'Gate Out', 'In yard'], $rows);
        $this->warn(sprintf(
            '%d session(s), %d container-days in the yard with no electricity charged.',
            $sessions->count(),
            $totalDays,
        ));

        if (! $this->option('mark-not-plugged')) {
            $this->newLine();
            $this->line('  A long stay on a laden reefer almost certainly means it <options=bold>was</> plugged in and');
            $this->line('  nobody recorded it — enter the plug times on the session screen so it can be');
            $this->line('  billed. Only where the box genuinely ran unplugged should it be marked:');
            $this->newLine();
            $this->line('    php artisan reefer:unplugged-sessions --mark-not-plugged --id=<id> --id=<id>');
            $this->newLine();
            $this->comment('Nothing has been changed.');

            return self::SUCCESS;
        }

        // Deliberately requires --id: marking every row at once is the blanket
        // re-label this command exists to avoid.
        if (! $this->option('id')) {
            $this->error('Pass --id for each session to mark. Marking them all at once is what this command exists to prevent.');

            return self::FAILURE;
        }

        $marked = ReeferPlugSession::whereIn('id', $sessions->pluck('id'))
            ->update(['status' => 'not_plugged', 'updated_by' => auth()->id()]);

        $this->info("{$marked} session(s) marked as never plugged in.");

        return self::SUCCESS;
    }
}
