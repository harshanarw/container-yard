<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageInvoiceDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_invoice_id', 'container_id', 'container_no', 'equipment_type',
        'gate_in_date', 'from_date', 'to_date',
        'total_days', 'free_days', 'chargeable_days',
        'daily_rate', 'currency', 'subtotal',
    ];

    protected $casts = [
        'gate_in_date' => 'date',
        'from_date'    => 'date',
        'to_date'      => 'date',
        'daily_rate'   => 'decimal:2',
        'subtotal'     => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function invoice()
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }
}
