<?php

namespace App\Services\Finance;

use App\Models\PaymentAllocation;
use App\Models\SupplierInvoice;
use Illuminate\Support\Collection;

/**
 * AP sub-ledger math — the payable counterpart to ArAllocationService.
 * Tracks which supplier invoices a payment voucher settles. Never touches the
 * GL (the voucher's confirm journal already relieves the payable).
 */
class ApAllocationService
{
    public function resolveInvoice(int $id): SupplierInvoice
    {
        $invoice = SupplierInvoice::find($id);
        if (!$invoice) {
            throw new \RuntimeException("Supplier invoice #{$id} not found.");
        }
        return $invoice;
    }

    public function getTotal(SupplierInvoice $invoice): float
    {
        return (float) ($invoice->total_amount ?? 0);
    }

    /**
     * The rate the bill was booked at (base-currency units per 1 invoice-currency
     * unit), used to relieve AP at its original value. Defaults to 1.
     */
    public function getExchangeRate(SupplierInvoice $invoice): float
    {
        return (float) ($invoice->exchange_rate ?? 1) ?: 1.0;
    }

    /**
     * Sum of allocations from non-voided vouchers for this invoice.
     */
    public function getAllocatedTotal(int $invoiceId): float
    {
        return (float) PaymentAllocation::where('supplier_invoice_id', $invoiceId)
            ->whereHas('voucher', fn ($q) => $q->where('status', 'confirmed'))
            ->sum('allocated_amount');
    }

    public function getOutstanding(SupplierInvoice $invoice): float
    {
        return max(0.0, round($this->getTotal($invoice) - $this->getAllocatedTotal($invoice->id), 4));
    }

    /**
     * Recalculate and persist the invoice status after any allocation change.
     * Only acts on payable statuses; leaves draft/cancelled untouched.
     */
    public function syncInvoiceStatus(SupplierInvoice $invoice): void
    {
        $current = $invoice->status;
        if (!in_array($current, ['approved', 'partially_paid', 'paid'])) {
            return;
        }

        $total     = $this->getTotal($invoice);
        $allocated = $this->getAllocatedTotal($invoice->id);

        if ($total <= 0) {
            return;
        }

        if ($allocated >= round($total - 0.005, 2)) {
            $newStatus = 'paid';
        } elseif ($allocated > 0) {
            $newStatus = 'partially_paid';
        } else {
            $newStatus = 'approved';
        }

        if ($newStatus !== $current) {
            $invoice->update(['status' => $newStatus]);
        }
    }

    /**
     * All outstanding invoices for a contact, ready for an allocation dropdown.
     * Rows: id, invoice_no, invoice_date, total, outstanding, label
     */
    public function pendingForSupplier(int $customerId): Collection
    {
        return SupplierInvoice::where('customer_id', $customerId)
            ->whereIn('status', ['approved', 'partially_paid'])
            ->whereNotNull('journal_id') // only GL-posted bills can be settled
            ->orderByDesc('invoice_date')
            ->get()
            ->map(function ($inv) {
                $outstanding = $this->getOutstanding($inv);
                if ($outstanding <= 0) {
                    return null;
                }
                return [
                    'id'           => $inv->id,
                    'invoice_no'   => $inv->invoice_no,
                    'invoice_date' => $inv->invoice_date,
                    'total'        => $this->getTotal($inv),
                    'outstanding'  => $outstanding,
                    'label'        => "{$inv->invoice_no} — outstanding: " . number_format($outstanding, 2),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Load allocations for a given supplier invoice with their voucher details,
     * for display on the invoice show page.
     */
    public function settlementsFor(int $invoiceId)
    {
        return PaymentAllocation::where('supplier_invoice_id', $invoiceId)
            ->with(['voucher'])
            ->orderBy('id')
            ->get();
    }
}
