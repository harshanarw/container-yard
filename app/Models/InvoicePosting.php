<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePosting extends Model
{
    protected $fillable = [
        'invoice_type', 'invoice_id', 'journal_id', 'status',
        'posted_at', 'posted_by', 'error_message', 'created_by',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function journal()
    {
        return $this->belongsTo(GlJournal::class, 'journal_id');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'storage'          => 'Storage Invoice',
            'storage-handling' => 'Storage & Handling Invoice',
            'reefer'           => 'Reefer Electricity Invoice',
            'repair'           => 'Repair Invoice',
            'general'          => 'General Invoice',
            default            => ucfirst($type) . ' Invoice',
        };
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'posted'  => 'bg-success-subtle text-success',
            'pending' => 'bg-warning-subtle text-warning',
            'failed'  => 'bg-danger-subtle text-danger',
            'voided'  => 'bg-secondary-subtle text-secondary',
            default   => 'bg-light text-muted',
        };
    }
}
