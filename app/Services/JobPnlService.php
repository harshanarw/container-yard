<?php

namespace App\Services;

use App\Models\ReeferElectricityInvoiceLine;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoiceLine;
use App\Models\StorageInvoiceDetail;
use App\Models\YardJob;
use App\Models\YardStorage;

class JobPnlService
{
    /**
     * Compute a P&L summary for a yard job.
     *
     * Revenue is aggregated by looking up all container IDs that entered
     * via this job's gate movements, then querying each billing stream.
     * Amounts are pre-tax subtotals in invoice currency.
     */
    public function compute(YardJob $yardJob): array
    {
        $containerIds = $yardJob->movements()
            ->pluck('container_id')
            ->filter()
            ->values();

        if ($containerIds->isEmpty()) {
            return $this->blank();
        }

        // ── Storage: accrued (live calculation from YardStorage) ──────────────
        $storageRows = YardStorage::whereIn('container_id', $containerIds)->get();
        $storageAccrued      = (float) $storageRows->sum('subtotal');
        $storageChargeableDays = (int)  $storageRows->sum('chargeable_days');
        $storageDailyRate    = $storageRows->avg('daily_rate');

        // ── Storage invoiced via standalone Storage Invoices ──────────────────
        $storageInvoicedA = (float) StorageInvoiceDetail::whereIn('container_id', $containerIds)
            ->whereHas('invoice', fn($q) => $q->whereNotIn('status', ['cancelled']))
            ->sum('subtotal');

        // ── Storage + Handling combined invoices ──────────────────────────────
        $shLines = StorageHandlingInvoiceLine::whereIn('container_id', $containerIds)
            ->whereHas('invoice', fn($q) => $q->whereNotIn('status', ['cancelled']))
            ->get();

        $storageInvoicedB = (float) $shLines->sum('storage_subtotal');
        $handlingInvoiced = (float) $shLines->sum('handling_subtotal');

        // ── Reefer electricity ────────────────────────────────────────────────
        $reeferInvoiced = (float) ReeferElectricityInvoiceLine::whereIn('container_id', $containerIds)
            ->whereHas('invoice', fn($q) => $q->whereNotIn('status', ['cancelled']))
            ->sum('subtotal');

        // ── Repair ────────────────────────────────────────────────────────────
        $repairInvoiced = (float) RepairInvoice::whereIn('container_id', $containerIds)
            ->whereNotIn('status', ['cancelled', 'void'])
            ->sum('subtotal');

        $storageInvoiced = $storageInvoicedA + $storageInvoicedB;
        $totalInvoiced   = $storageInvoiced + $handlingInvoiced + $reeferInvoiced + $repairInvoiced;

        return [
            'container_count'          => $containerIds->count(),
            'storage_accrued'          => $storageAccrued,
            'storage_chargeable_days'  => $storageChargeableDays,
            'storage_daily_rate'       => $storageDailyRate,
            'storage_invoiced'         => $storageInvoiced,
            'handling_invoiced'        => $handlingInvoiced,
            'reefer_invoiced'          => $reeferInvoiced,
            'repair_invoiced'          => $repairInvoiced,
            'total_invoiced'           => $totalInvoiced,
            'has_data'                 => $totalInvoiced > 0 || $storageAccrued > 0,
        ];
    }

    private function blank(): array
    {
        return [
            'container_count'         => 0,
            'storage_accrued'         => 0.0,
            'storage_chargeable_days' => 0,
            'storage_daily_rate'      => null,
            'storage_invoiced'        => 0.0,
            'handling_invoiced'       => 0.0,
            'reefer_invoiced'         => 0.0,
            'repair_invoiced'         => 0.0,
            'total_invoiced'          => 0.0,
            'has_data'                => false,
        ];
    }
}
