<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptAllocation extends Model
{
    protected $fillable = [
        'receipt_id',
        'invoice_type',
        'invoice_id',
        'allocated_amount',
        'base_amount',
        'notes',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:4',
        'base_amount'      => 'decimal:4',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'storage'          => 'Storage Invoice',
            'storage-handling' => 'Storage & Handling Invoice',
            'reefer'           => 'Reefer Electricity Invoice',
            'repair'           => 'Repair Invoice',
            default            => ucfirst($type) . ' Invoice',
        };
    }
}
