<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class YardJob extends Model
{
    protected $fillable = [
        'job_no', 'job_seq',
        'job_type_id', 'job_type_code', 'type_short_code',
        'customer_id',
        'status',
        'started_at', 'completed_at',
        'remarks',
        'created_by', 'closed_by',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Job number generation ─────────────────────────────────────────────────

    /**
     * Atomically claim the next sequence number for this job type and build
     * the job number string.  Returns ['job_no' => '...', 'job_seq' => N].
     */
    public static function generateJobNo(YardJobType $type): array
    {
        $seq    = (static::where('job_type_id', $type->id)->max('job_seq') ?? 0) + 1;
        $prefix = CompanySetting::current()->company_prefix ?? 'YD';
        $jobNo  = sprintf('%s-%s-%05d', $prefix, $type->type_short_code, $seq);

        return ['job_no' => $jobNo, 'job_seq' => $seq];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function jobType()
    {
        return $this->belongsTo(YardJobType::class, 'job_type_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function movements()
    {
        return $this->hasMany(GateMovement::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'open');
    }

    public function scopeByStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'open'        => 'bg-primary',
            'in_progress' => 'bg-warning text-dark',
            'completed'   => 'bg-success',
            'cancelled'   => 'bg-secondary',
            default       => 'bg-light text-dark border',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'completed'   => 'Completed',
            'cancelled'   => 'Cancelled',
            default       => ucfirst($status),
        };
    }

    public function isCloseable(): bool
    {
        return in_array($this->status, ['open', 'in_progress']);
    }
}
