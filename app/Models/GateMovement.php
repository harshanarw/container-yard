<?php

namespace App\Models;

use App\Traits\HasApprovals;
use App\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GateMovement extends Model
{
    use HasFactory, HasDocuments, HasApprovals;

    protected $fillable = [
        'container_id', 'survey_id', 'job_type_id', 'job_type_code', 'yard_job_id',
        'container_no', 'customer_id', 'transporter_id', 'movement_type', 'eir_no', 'size',
        'container_type', 'ventilation_type', 'vent_count',
        'location_zone', 'location_row', 'location_bay', 'location_tier',
        'condition', 'grade_id', 'cargo_status', 'reefer_mode', 'seal_no', 'no_seal_reason', 'vehicle_plate', 'driver_name',
        'driver_ic', 'driver_phone', 'release_order', 'gate_in_time', 'gate_out_time',
        'movement_status', 'remarks', 'created_by',
        // Gate-In: import shipment information
        'vessel_name', 'voyage_no', 'berthing_date', 'bl_number', 'do_expiry_date', 'fcl_expiry_date', 'consignee',
        // Gate-In: overtime receipt link
        'ot_receipt_id', 'is_overtime', 'ot_override_reason',
        // Gate-Out: export information
        'loading_vessel', 'loading_voyage', 'sailing_date', 'shipper',
        'gate_out_purpose', 'container_booking_id',
        'codeco_exported_at', 'csv_exported_at',
        'codeco_exported_by', 'csv_exported_by',
        'codeco_batch_ref', 'csv_batch_ref',
        'container_ocr_image_path', 'plate_ocr_image_path',
        'share_code', 'share_expires_at',
    ];

    /** Days a freshly-sent driver gate-pass link stays valid. */
    public const SHARE_LINK_DAYS = 7;

    protected static function booted(): void
    {
        // Give every new movement a short, unguessable code for the shareable
        // driver gate-pass link (/g/{code}).
        static::creating(function (GateMovement $movement) {
            if (empty($movement->share_code)) {
                $movement->share_code = static::generateShareCode();
            }
        });
    }

    public static function generateShareCode(): string
    {
        do {
            $code = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(12));
        } while (static::where('share_code', $code)->exists());

        return $code;
    }

    /** Start (or refresh) the share link's validity window from now. */
    public function refreshShareLink(): void
    {
        if (empty($this->share_code)) {
            $this->share_code = static::generateShareCode();
        }
        $this->share_expires_at = now()->addDays(self::SHARE_LINK_DAYS);
        $this->save();
    }

    /** True while the driver share link is live (sent and not yet expired). */
    public function shareLinkIsValid(): bool
    {
        return $this->share_code
            && $this->share_expires_at
            && $this->share_expires_at->isFuture();
    }

    protected $casts = [
        'vent_count'         => 'integer',
        'share_expires_at'   => 'datetime',
        'gate_in_time'       => 'datetime',
        'gate_out_time'      => 'datetime',
        'berthing_date'      => 'date',
        'do_expiry_date'     => 'date',
        'fcl_expiry_date'    => 'date',
        'sailing_date'       => 'date',
        'codeco_exported_at' => 'datetime',
        'csv_exported_at'    => 'datetime',
        // This cycle's M&R status — written only by ContainerMrStatusService,
        // on gate-IN rows. Live while the cycle is open, terminal once it closes.
        'mr_status_at'       => 'datetime',
    ];

    /** Non-Operating Reefer: a reefer box whose machinery is off for this visit. */
    public function isNonOperatingReefer(): bool
    {
        return $this->reefer_mode === 'non_operating';
    }

    /**
     * Whether this movement should carry a visible NOR label.
     *
     * Laden only. An empty reefer with its machinery off is the ordinary state
     * of an empty reefer and flagging it would be noise; a *loaded* one is the
     * exception worth pointing at, because it is the case where someone might
     * otherwise expect a plug, a set point and a PTI.
     */
    public function isLadenNor(): bool
    {
        return $this->isNonOperatingReefer() && $this->cargo_status === 'laden';
    }

    /**
     * Whether the reefer machinery is in use for this visit.
     *
     * Null on a reefer reads as operating: every movement recorded before this
     * column existed behaved that way, and re-interpreting them as NOR would
     * rewrite history the yard never entered.
     */
    public function isOperatingReefer(): bool
    {
        return $this->reefer_mode !== 'non_operating';
    }

    // Relationships
    public function yardJob()
    {
        return $this->belongsTo(\App\Models\YardJob::class, 'yard_job_id');
    }

    public function jobType()
    {
        return $this->belongsTo(\App\Models\YardJobType::class, 'job_type_id');
    }

    public function grade()
    {
        return $this->belongsTo(\App\Models\ContainerGrade::class, 'grade_id');
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    /** The Guard Post capture this movement was promoted from, if any. */
    public function guardCapture()
    {
        return $this->hasOne(\App\Models\GuardCapture::class, 'linked_gate_movement_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transporter()
    {
        return $this->belongsTo(Customer::class, 'transporter_id');
    }

    public function otReceipt()
    {
        return $this->belongsTo(\App\Models\OtReceipt::class, 'ot_receipt_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function survey()
    {
        return $this->belongsTo(\App\Models\Inquiry::class, 'survey_id');
    }

    public function photos()
    {
        return $this->hasMany(GateMovementPhoto::class);
    }

    public function codecoExportedBy()
    {
        return $this->belongsTo(User::class, 'codeco_exported_by');
    }

    public function csvExportedBy()
    {
        return $this->belongsTo(User::class, 'csv_exported_by');
    }

    public function isPendingCodecoExport(): bool
    {
        return is_null($this->codeco_exported_at);
    }

    public function isPendingCsvExport(): bool
    {
        return is_null($this->csv_exported_at);
    }

    public function getContainerOcrImageUrlAttribute(): ?string
    {
        return $this->container_ocr_image_path
            ? asset('storage/' . $this->container_ocr_image_path)
            : null;
    }

    public function getPlateOcrImageUrlAttribute(): ?string
    {
        return $this->plate_ocr_image_path
            ? asset('storage/' . $this->plate_ocr_image_path)
            : null;
    }

    /**
     * The party taking or returning the container at this gate.
     *
     * The counterpart of {@see scopeBillableTo()} for a single movement, and
     * the same rule: the job's holder, falling back to the visit customer. On
     * an ordinary movement `held_by` is null and this simply *is* the visit
     * customer; on a rental release or return it is the renter.
     *
     * `customer_id` stays the visit customer — the box is on the shipping
     * line's stay, which is a fact worth keeping and which
     * `containers:fix-gate-custody` exists to protect. Who is standing at the
     * gate is a different question, and this answers it.
     */
    public function holdingParty(): ?Customer
    {
        return $this->yardJob?->holder() ?? $this->customer;
    }

    /** True when the party at the gate is not the party whose visit this is. */
    public function heldByAnotherParty(): bool
    {
        $holder = $this->holdingParty();

        return $holder !== null
            && $this->customer_id !== null
            && (int) $holder->id !== (int) $this->customer_id;
    }

    /**
     * The party, in words, for a screen or a file.
     *
     * One string, so ten surfaces cannot each decide how to say it. Where the
     * holder and the visit customer are the same — every ordinary movement —
     * this is just the customer's name and reads exactly as it always did.
     */
    public function partyLabel(): string
    {
        $holder = $this->holdingParty();

        if (! $holder) {
            return '-';
        }

        return $this->heldByAnotherParty()
            ? $holder->name . ' (on hire from ' . ($this->customer?->name ?? 'owner') . ')'
            : $holder->name;
    }

    /**
     * Every job this movement sits under, outermost first.
     *
     * A rental puts three jobs on one container and the movement carries only
     * the innermost. The rest are one walk up `parent_job_id`:
     *
     *   Gate-in job          the shipping line's stay
     *     On-hire (lease)    the yard holds it from the line
     *       Rent job         a customer has it out
     *
     * Bounded and cycle-guarded for the same reason
     * {@see \App\Services\JobPnlService::computeWithSubJobs()} is: the column is
     * self-referential, and a mis-set parent must not hang a screen.
     *
     * @return array<int,array{label:string,job_no:string,job_type:?string,party:?string}>
     */
    public function jobChain(int $maxDepth = 5): array
    {
        $chain   = [];
        $visited = [];
        $job     = $this->yardJob;

        while ($job && count($chain) < $maxDepth && ! isset($visited[$job->id])) {
            $visited[$job->id] = true;

            $chain[] = [
                'label'    => match ($job->job_type_code) {
                    'CONTAINER_RELET' => 'Rent job',
                    'LESSOR_ONHIRE'   => 'On-hire (lease) job',
                    default           => 'Gate-in job',
                },
                'job_no'   => $job->job_no,
                'job_type' => $job->job_type_code,
                // The holder, not the counterparty: on a lease the line is
                // still who the agreement is with, but the yard is holding it.
                'party'    => $job->holder()?->name,
            ];

            $job = $job->parentJob;
        }

        return array_reverse($chain);
    }

    /**
     * Movements this party should be billed the lift for.
     *
     * **The party holding the container pays for the lift.** For nearly every
     * movement that is the visit customer, and `customer_id` says so directly.
     * A rental is the exception: the yard lifts the box onto a renter's truck,
     * and the renter is not the party whose visit it is.
     *
     * The movement deliberately keeps the *visit* customer in `customer_id` —
     * the box is on the shipping line's stay, that is a real fact, and
     * `containers:fix-gate-custody` exists to force it back when it drifts. Who
     * is holding the box is a different question, answered by the job:
     * `held_by_customer_id` is the renter on a re-let, the yard on a lease-in,
     * and null on everything else.
     *
     * So handling selected by `customer_id` alone billed the shipping line for
     * lifting a container onto the truck of a customer renting it from the
     * yard — a party the line has no relationship with.
     *
     * Null `held_by` falls through to `customer_id`, so every ordinary movement
     * is selected exactly as it was before this existed.
     */
    public function scopeBillableTo($query, ?int $customerId)
    {
        if (! $customerId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($customerId) {
            // The job names a holder, and it is this party.
            $q->whereHas('yardJob', fn ($j) => $j->where('held_by_customer_id', $customerId))
              // Or no holder is named, and the visit is this party's.
              ->orWhere(fn ($w) => $w
                  ->where('customer_id', $customerId)
                  ->whereDoesntHave('yardJob', fn ($j) => $j->whereNotNull('held_by_customer_id')));
        });
    }
}
