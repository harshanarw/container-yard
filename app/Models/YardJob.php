<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class YardJob extends Model
{
    protected $fillable = [
        'parent_job_id',
        'job_no', 'job_seq',
        'job_type_id', 'job_type_code', 'type_short_code',
        'customer_id',
        // Which way the money flows, and who is holding the box. See the
        // constants below — a lease-in is the case that separates them.
        'billing_direction',
        'held_by_customer_id',
        'status',
        'started_at', 'completed_at',
        'remarks',
        'return_reason',
        'created_by', 'closed_by',
    ];

    /** The yard bills the counterparty. Every ordinary job. */
    public const DIRECTION_RECEIVABLE = 'ar';

    /** The counterparty bills the yard. A lease-in from a shipping line. */
    public const DIRECTION_PAYABLE = 'ap';

    /**
     * The database default again, in memory.
     *
     * A column default only applies to the *row*; the model `create()` hands
     * back still has the attribute unset, so `$job->billing_direction` reads
     * null until something refetches it. Every caller would then need a
     * `?? 'ar'`, and the one that forgot would treat an ordinary job as
     * neither direction.
     */
    protected $attributes = [
        'billing_direction' => self::DIRECTION_RECEIVABLE,
    ];

    public static function returnReasons(): array
    {
        return [
            'import_consignee' => 'Import Consignee Return',
            'agent_return'     => 'Shipping Line / Agent Return',
            'shipper_return'   => 'Shipper Return (Defect / Rejection)',
        ];
    }

    public function returnReasonLabel(): ?string
    {
        return $this->return_reason
            ? (static::returnReasons()[$this->return_reason] ?? ucfirst($this->return_reason))
            : null;
    }

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
        $prefix = strtoupper(CompanySetting::current()->company_prefix ?? 'YD');
        $jobNo  = sprintf('%s-%s-%05d', $prefix, $type->type_short_code, $seq);

        return ['job_no' => $jobNo, 'job_seq' => $seq];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * Who has physical custody during this job, when it is not the counterparty.
     *
     * The lease-in is what separates the two questions:
     *
     *   Gate In    counterparty: the line       held by: the line
     *   Lease-In   counterparty: the line (AP)  held by: the yard
     *   Rental     counterparty: a customer     held by: that customer
     *
     * Null means the counterparty holds it, which is true of every ordinary
     * job — so null is the honest default rather than a missing value.
     */
    public function heldBy()
    {
        return $this->belongsTo(Customer::class, 'held_by_customer_id');
    }

    /** The party physically holding the container during this job. */
    public function holder(): ?Customer
    {
        return $this->heldBy ?? $this->customer;
    }

    /** True when the counterparty invoices the yard rather than the reverse. */
    public function isPayable(): bool
    {
        return $this->billing_direction === self::DIRECTION_PAYABLE;
    }

    public function isReceivable(): bool
    {
        return ! $this->isPayable();
    }

    /** Jobs the yard is billed for — lease-in fees and the like. */
    public function scopePayable($query)
    {
        return $query->where('billing_direction', self::DIRECTION_PAYABLE);
    }

    /** Jobs the yard bills out. The default, and nearly everything. */
    public function scopeReceivable($query)
    {
        return $query->where('billing_direction', self::DIRECTION_RECEIVABLE);
    }

    /**
     * The job this one happens inside, if any.
     *
     * A sub-job is something with its own counterparty, dates and P&L that
     * occurs during another job's lifetime — the yard taking a box on hire from
     * the line, sub-hiring it onward, or transferring cargo into a substitute.
     * Each keeps its own ledger lines; the parent rolls them up.
     */
    public function parentJob()
    {
        return $this->belongsTo(YardJob::class, 'parent_job_id');
    }

    /** The sub-jobs opened during this job. */
    public function subJobs()
    {
        return $this->hasMany(YardJob::class, 'parent_job_id');
    }

    /** True when this job happens inside another. */
    public function isSubJob(): bool
    {
        return $this->parent_job_id !== null;
    }

    /**
     * Jobs with no parent.
     *
     * Listings default to this: a sub-hire appearing beside the stay it belongs
     * to reads as two unrelated jobs on the same container, which is how a
     * count of "jobs this month" ends up double.
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_job_id');
    }

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

    public function gateOut()
    {
        return $this->hasOne(GateMovement::class)->where('movement_type', 'out');
    }

    /** Cargo rental / container-substitution swaps tracked under this job. */
    public function cargoTransfers()
    {
        return $this->hasMany(CargoTransfer::class);
    }

    /**
     * The job's primary container — the gate-in movement's container (falls back
     * to any movement's). Used to derive the container for job-costing tags.
     */
    public function primaryContainerId(): ?int
    {
        return $this->movements()->where('movement_type', 'in')->orderBy('id')->value('container_id')
            ?? $this->movements()->orderBy('id')->value('container_id');
    }

    /**
     * Enriched active-job list for the job-costing picker: each job with its
     * customer and primary container (no + size/type) so the dropdown can show
     * "JOB-NO · CONT-NO · 40'HC · Customer" and filter by party (customer_id).
     */
    public static function pickerData(int $limit = 500): \Illuminate\Support\Collection
    {
        return static::query()
            ->with([
                'customer:id,name',
                'movements' => fn ($q) => $q->where('movement_type', 'in')->orderBy('id')
                    ->with('container:id,container_no,size,type_code'),
            ])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($j) {
                $c = optional($j->movements->first())->container;
                return [
                    'id'           => $j->id,
                    'job_no'       => $j->job_no,
                    'short'        => $j->type_short_code,
                    'type'         => $j->job_type_code,
                    'customer_id'  => $j->customer_id,
                    'customer'     => $j->customer?->name,
                    'container_no' => $c?->container_no,
                    'size'         => $c?->size,
                    'type_code'    => $c?->type_code,
                ];
            })
            ->values();
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
