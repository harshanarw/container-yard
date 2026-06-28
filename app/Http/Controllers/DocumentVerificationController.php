<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Estimate;
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
            'docLabel' => $this->label($type),
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
                'number'   => $i->invoice_no,
                'date'     => $i->invoice_date?->format('d M Y'),
                'party'    => $i->shippingLine?->name,
                'amount'   => (float) $i->total_amount,
                'currency' => $i->invoice_currency,
                'status'   => $i->status,
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

            'estimate' => ($i = Estimate::with('customer')->find($id)) ? [
                'number'   => $i->estimate_no,
                'date'     => $i->estimate_date?->format('d M Y'),
                'party'    => $i->customer?->name,
                'amount'   => (float) $i->grand_total,
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
            'estimate'         => 'Repair Estimate',
        ][$type] ?? 'Document';
    }
}
