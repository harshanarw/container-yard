<?php

namespace App\Http\Controllers;

use App\Models\ApCreditNote;
use App\Models\ArCreditNote;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use Illuminate\Http\Request;

/**
 * Public, signed-URL verification of issued documents. The QR code printed on a
 * document PDF points here; scanning it shows the authentic details straight
 * from the database so a recipient can confirm the document is genuine.
 *
 * No authentication — anyone with the (signed) link can verify. The 'signed'
 * middleware guarantees the link was issued by this system and was not altered.
 */
class DocumentVerificationController extends Controller
{
    public function show(Request $request, string $type, int $id)
    {
        $doc = $this->resolve($type, $id);

        $company = CompanySetting::current();

        if (! $doc) {
            return response()->view('documents.verify', [
                'company'  => $company,
                'found'    => false,
                'docLabel' => $this->label($type),
            ], 404);
        }

        return view('documents.verify', array_merge([
            'company'  => $company,
            'found'    => true,
            // A resolved document may carry a per-record label (e.g. the storage &
            // handling bill type); otherwise fall back to the static type label.
            'docLabel' => $doc['doc_label'] ?? $this->label($type),
        ], $doc));
    }

    /**
     * Resolve a document to a normalised set of display fields, or null.
     *
     * @return array{number:string,date:?string,party:?string,amount:?float,currency:?string,status:?string}|null
     */
    private function resolve(string $type, int $id): ?array
    {
        return match ($type) {
            'storage' => ($i = StorageInvoice::with('customer')->find($id)) ? [
                'number'   => $i->invoice_no,
                'date'     => $i->invoice_date?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->total_amount,
                'currency' => $i->invoice_currency,
                'status'   => $i->status,
            ] : null,

            'storage-handling' => ($i = StorageHandlingInvoice::with('shippingLine')->find($id)) ? [
                'number'    => $i->invoice_no,
                'date'      => $i->invoice_date?->format('d M Y'),
                'party'     => $i->shippingLine?->name,
                'amount'    => (float) $i->total_amount,
                'currency'  => $i->invoice_currency,
                'status'    => $i->status,
                // Per-record label so the verifier sees the exact bill type.
                'doc_label' => $i->bill_type_label . ' Invoice',
            ] : null,

            'reefer' => ($i = ReeferElectricityInvoice::with('customer')->find($id)) ? [
                'number'   => $i->invoice_no,
                'date'     => $i->invoice_date?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->total_amount,
                'currency' => $i->invoice_currency,
                'status'   => $i->status,
            ] : null,

            'repair' => ($i = RepairInvoice::with('customer')->find($id)) ? [
                'number'   => $i->invoice_no,
                'date'     => $i->invoice_date?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->grand_total,
                'currency' => $i->currency,
                'status'   => $i->status,
            ] : null,

            'general' => ($i = \App\Models\GeneralInvoice::with('customer', 'billingParty')->find($id)) ? [
                'number'    => $i->invoice_no,
                'date'      => $i->invoice_date?->format('d M Y'),
                'party'     => ($i->billingParty ?? $i->customer)?->name,
                'amount'    => (float) $i->grand_total,
                'currency'  => $i->currency,
                'status'    => $i->status,
                'doc_label' => $i->type_label,
            ] : null,

            'estimate' => ($i = Estimate::with('customer')->find($id)) ? [
                'number'   => $i->estimate_no,
                'date'     => $i->estimate_date?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->grand_total,
                'currency' => $i->currency,
                'status'   => $i->status,
            ] : null,

            'receipt' => ($i = Receipt::with('customer')->find($id)) ? [
                'number'   => $i->receipt_no,
                'date'     => $i->receipt_date?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->amount,
                'currency' => $i->currency,
                'status'   => $i->status,
            ] : null,

            'ot-receipt' => ($i = \App\Models\OtReceipt::with('customer')->find($id)) ? [
                'number'   => $i->receipt_no,
                'date'     => $i->created_at?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->total_amount,
                'currency' => $i->currency,
                'status'   => $i->status,
                'bl'       => $i->bl_number,
                'valid_to' => $i->valid_to?->format('d M Y H:i'),
            ] : null,

            'voucher' => ($i = PaymentVoucher::with('supplier')->find($id)) ? [
                'number'   => $i->voucher_no,
                'date'     => $i->voucher_date?->format('d M Y'),
                'party'    => $i->supplier?->name ?: $i->payee_name,
                'amount'   => (float) $i->amount,
                'currency' => $i->currency,
                'status'   => $i->status,
            ] : null,

            'ar-credit-note' => ($i = ArCreditNote::with('customer')->find($id)) ? [
                'number'   => $i->credit_note_no,
                'date'     => $i->credit_date?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->total_amount,
                'currency' => $i->currency,
                'status'   => $i->status,
            ] : null,

            'ap-credit-note' => ($i = ApCreditNote::with('supplier')->find($id)) ? [
                'number'   => $i->credit_note_no,
                'date'     => $i->credit_date?->format('d M Y'),
                'party'    => $i->supplier?->name,
                'amount'   => (float) $i->total_amount,
                'currency' => $i->currency,
                'status'   => $i->status,
            ] : null,

            default => null,
        };
    }

    private function label(string $type): string
    {
        return [
            'storage'          => 'Storage Invoice',
            'storage-handling' => 'Storage & Handling Invoice',
            'reefer'           => 'Reefer Electricity Invoice',
            'repair'           => 'Repair Invoice',
            'general'          => 'General Invoice',
            'estimate'         => 'Repair Estimate',
            'receipt'          => 'Receipt',
            'ot-receipt'       => 'Overtime Receipt',
            'voucher'          => 'Payment Voucher',
            'ar-credit-note'   => 'Credit Note',
            'ap-credit-note'   => 'Debit Note',
        ][$type] ?? 'Document';
    }
}
