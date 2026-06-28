<?php

namespace App\Services\Finance;

use App\Models\ReceiptAllocation;
use App\Models\StorageInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ArAllocationService
{
    private static array $modelMap = [
        'storage'          => StorageInvoice::class,
        'storage-handling' => StorageHandlingInvoice::class,
        'reefer'           => ReeferElectricityInvoice::class,
        'repair'           => RepairInvoice::class,
    ];

    public function resolveInvoice(string $type, int $id): Model
    {
        $class = self::$modelMap[$type] ?? null;
        if (!$class) {
            throw new \InvalidArgumentException("Unknown invoice type: {$type}");
        }
        $invoice = $class::find($id);
        if (!$invoice) {
            throw new \RuntimeException("Invoice {$type}#{$id} not found.");
        }
        return $invoice;
    }

    public function getCustomerId(Model $invoice, string $type): ?int
    {
        $id = $type === 'storage-handling'
            ? ($invoice->shipping_line_id ?? $invoice->customer_id ?? null)
            : ($invoice->customer_id ?? null);

        return $id ? (int) $id : null;
    }

    public function getTotal(Model $invoice, string $type): float
    {
        return (float) ($type === 'repair' ? ($invoice->grand_total ?? 0) : ($invoice->total_amount ?? 0));
    }

    /**
     * The rate the invoice was booked at (base-currency units per 1 invoice-currency
     * unit). All four AR invoice types now carry an exchange_rate; defaults to 1.
     */
    public function getExchangeRate(Model $invoice, string $type): float
    {
        return (float) ($invoice->exchange_rate ?? 1) ?: 1.0;
    }

    /**
     * Sum of allocations from non-voided receipts for this invoice.
     */
    public function getAllocatedTotal(string $type, int $id): float
    {
        $receipts = (float) ReceiptAllocation::where('invoice_type', $type)
            ->where('invoice_id', $id)
            ->whereHas('receipt', fn ($q) => $q->where('status', 'confirmed'))
            ->sum('allocated_amount');

        // Approved AR credit notes applied to this invoice also settle it (non-cash).
        $creditNotes = (float) \App\Models\ArCreditNoteApplication::where('invoice_type', $type)
            ->where('invoice_id', $id)
            ->whereHas('creditNote', fn ($q) => $q->where('status', 'approved'))
            ->sum('applied_amount');

        return $receipts + $creditNotes;
    }

    public function getOutstanding(Model $invoice, string $type): float
    {
        return max(0.0, round($this->getTotal($invoice, $type) - $this->getAllocatedTotal($type, $invoice->id), 4));
    }

    /**
     * Recalculate and persist the invoice status after any allocation change.
     * Only acts on receivable statuses; leaves draft/cancelled/voided untouched.
     */
    public function syncInvoiceStatus(Model $invoice, string $type): void
    {
        $current = $invoice->status;
        if (!in_array($current, ['issued', 'partially_paid', 'paid', 'overdue'])) {
            return;
        }

        $total     = $this->getTotal($invoice, $type);
        $allocated = $this->getAllocatedTotal($type, $invoice->id);

        if ($total <= 0) {
            return;
        }

        // Repair invoices also carry their own amount_paid (Record Payment) and a
        // balance_due column. Combine both settlement sources (manual payments +
        // AR allocations from receipts/credit notes) so the repair invoice's own
        // page balance and status stay correct.
        if ($type === 'repair') {
            $settled = round((float) $invoice->amount_paid + $allocated, 2);

            if ($settled >= round($total - 0.005, 2)) {
                $newStatus = 'paid';
            } elseif ($settled > 0) {
                $newStatus = 'partially_paid';
            } else {
                $newStatus = in_array($current, ['paid', 'partially_paid']) ? 'issued' : $current;
            }

            $invoice->update([
                'balance_due' => round(max(0, $total - $settled), 2),
                'status'      => $newStatus,
            ]);
            return;
        }

        // Other AR types have no partial-paid status; outstanding is derived from
        // allocations, so only the status needs syncing.
        if ($allocated >= $total) {
            $newStatus = 'paid';
        } else {
            $newStatus = in_array($current, ['paid', 'partially_paid']) ? 'issued' : $current;
        }

        if ($newStatus !== $current) {
            $invoice->update(['status' => $newStatus]);
        }
    }

    /**
     * All outstanding invoices for a customer, ready for an allocation dropdown.
     * Returns a flat Collection of arrays with keys:
     *   type, id, invoice_no, invoice_date, total, outstanding, label
     */
    public function pendingForCustomer(int $customerId): Collection
    {
        $pending = collect();

        // Storage invoices (status enum has no 'overdue' — past-due is derived from due_date)
        StorageInvoice::where('customer_id', $customerId)
            ->where('status', 'issued')
            ->orderByDesc('invoice_date')
            ->get()
            ->each(function ($inv) use (&$pending) {
                $outstanding = $this->getOutstanding($inv, 'storage');
                if ($outstanding > 0) {
                    $pending->push($this->row($inv, 'storage', 'Storage', $outstanding));
                }
            });

        // Storage-handling invoices use shipping_line_id
        StorageHandlingInvoice::where('shipping_line_id', $customerId)
            ->where('status', 'issued')
            ->orderByDesc('invoice_date')
            ->get()
            ->each(function ($inv) use (&$pending) {
                $outstanding = $this->getOutstanding($inv, 'storage-handling');
                if ($outstanding > 0) {
                    $pending->push($this->row($inv, 'storage-handling', 'Handling', $outstanding));
                }
            });

        // Reefer invoices
        ReeferElectricityInvoice::where('customer_id', $customerId)
            ->where('status', 'issued')
            ->orderByDesc('invoice_date')
            ->get()
            ->each(function ($inv) use (&$pending) {
                $outstanding = $this->getOutstanding($inv, 'reefer');
                if ($outstanding > 0) {
                    $pending->push($this->row($inv, 'reefer', 'Reefer', $outstanding));
                }
            });

        // Repair invoices (also includes partially_paid)
        RepairInvoice::where('customer_id', $customerId)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->orderByDesc('invoice_date')
            ->get()
            ->each(function ($inv) use (&$pending) {
                $outstanding = $this->getOutstanding($inv, 'repair');
                if ($outstanding > 0) {
                    $pending->push($this->row($inv, 'repair', 'Repair', $outstanding));
                }
            });

        return $pending->sortByDesc('invoice_date')->values();
    }

    /**
     * Load allocations for a given invoice with their receipt details, for display
     * on invoice show pages.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function settlementsFor(string $type, int $id)
    {
        return ReceiptAllocation::where('invoice_type', $type)
            ->where('invoice_id', $id)
            ->with(['receipt' => fn ($q) => $q->with('customer')])
            ->orderBy('id')
            ->get();
    }

    private function row(Model $inv, string $type, string $label, float $outstanding): array
    {
        // Repair invoices carry a due_date column directly; the other AR types
        // gained one in migration 000209. Fall back to the invoice date for any
        // legacy row still lacking one so the allocation UI always has a value.
        $dueDate = !empty($inv->due_date)
            ? \Carbon\Carbon::parse($inv->due_date)
            : ($inv->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date) : null);
        $pastDue = $dueDate ? \Carbon\Carbon::today()->gt($dueDate) : false;

        return [
            'type'         => $type,
            'id'           => $inv->id,
            'invoice_no'   => $inv->invoice_no,
            'invoice_date' => $inv->invoice_date,
            'due_date'     => $dueDate,
            'past_due'     => $pastDue,
            'currency'     => strtoupper((string) ($inv->invoice_currency ?? $inv->currency ?? '')),
            'total'        => $this->getTotal($inv, $type),
            'outstanding'  => $outstanding,
            'label'        => "[{$label}] {$inv->invoice_no} — outstanding: " . number_format($outstanding, 2)
                              . ($dueDate ? ' · due ' . $dueDate->format('d M Y') : '')
                              . ($pastDue ? ' (PAST DUE)' : ''),
        ];
    }
}
