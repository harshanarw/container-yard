<?php

namespace App\Services;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\GateMovement;
use App\Models\ReeferPlugSession;
use App\Models\StorageMasterHeader;
use App\Models\YardJob;
use App\Models\YardLocation;
use App\Models\YardStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cargo rental / container substitution ("cross-stuffing").
 *
 * Given the gate-in of a customer's laden box (job type CARGO_RENTAL_IN), this
 * moves the cargo into a yard-owned / on-hired substitute box and gates the now
 * empty source box back out — all under the source's job. The substitute box
 * then accrues customer-billed storage (NO free days) and, when refrigerated,
 * reefer electricity.
 */
class CargoTransferService
{
    /**
     * @param  GateMovement  $sourceMovement  the CARGO_RENTAL_IN gate-in of the laden box
     * @param  array  $data  substitute_container_id, substitute_source, transfer_date,
     *                       cargo_description, handling_charge, daily_rate?, notes?
     */
    public function transfer(GateMovement $sourceMovement, array $data, int $userId): CargoTransfer
    {
        $transferDate = Carbon::parse($data['transfer_date']);
        $substitute   = Container::findOrFail($data['substitute_container_id']);
        $source       = $sourceMovement->container;

        $this->guard($sourceMovement, $source, $substitute);

        $job        = $sourceMovement->yardJob;
        $customerId = $sourceMovement->customer_id ?? $source->customer_id;
        $isReefer   = in_array($substitute->type_code, ['RF', 'RH'], true);

        return DB::transaction(function () use ($sourceMovement, $source, $substitute, $data, $transferDate, $userId, $job, $customerId, $isReefer) {
            // Storage rate for the substitute box (customer + equipment tariff).
            $dailyRate = isset($data['daily_rate']) && $data['daily_rate'] !== null && $data['daily_rate'] !== ''
                ? (float) $data['daily_rate']
                : $this->resolveDailyRate($customerId, $substitute->equipment_type_id, $transferDate);

            $transfer = CargoTransfer::create([
                'yard_job_id'             => $job?->id,
                'customer_id'             => $customerId,
                'source_container_id'     => $source->id,
                'source_gate_movement_id' => $sourceMovement->id,
                'substitute_container_id' => $substitute->id,
                'substitute_source'       => $data['substitute_source'] ?? CargoTransfer::SOURCE_YARD_OWNED,
                'is_reefer'               => $isReefer,
                'transfer_date'           => $transferDate->toDateString(),
                'cargo_description'       => $data['cargo_description'] ?? null,
                'handling_charge'         => (float) ($data['handling_charge'] ?? 0),
                'status'                  => 'active',
                'notes'                   => $data['notes'] ?? null,
                'created_by'              => $userId,
                'updated_by'              => $userId,
            ]);

            // Close any open (non-hire) storage the substitute box already had.
            YardStorage::where('container_id', $substitute->id)
                ->whereNull('gate_out_date')
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->update(['gate_out_date' => $transferDate->copy()->subDay()->toDateString()]);

            // Open the customer-billed laden storage on the substitute box — NO free
            // days (a commercial cargo-storage deal, not a container return).
            $storage = YardStorage::create([
                'container_id'  => $substitute->id,
                'customer_id'   => $customerId,
                'yard_job_id'   => $job?->id,
                'gate_in_date'  => $transferDate->toDateString(),
                'gate_out_date' => null,
                'free_days'     => 0,
                'daily_rate'    => $dailyRate,
                'hire_type'     => 'normal',
            ]);

            // The substitute box now holds cargo.
            $substitute->update([
                'status'            => 'in_yard',
                'cargo_status'      => 'laden',
                'status_changed_at' => now(),
            ]);

            // Reefer electricity — a pending plug session for the substitute box,
            // billed to the cargo customer. Anchored to the box's own gate-in
            // movement (the plug session FK is NOT NULL). Best-effort: if the box
            // has no gate-in movement on record, skip (session can be added later).
            $reeferSessionId = null;
            if ($isReefer) {
                $subMovement = GateMovement::where('container_id', $substitute->id)
                    ->where('movement_type', 'in')
                    ->latest('id')->first();
                if ($subMovement) {
                    $reeferSessionId = ReeferPlugSession::create([
                        'container_id'     => $substitute->id,
                        'gate_movement_id' => $subMovement->id,
                        'yard_job_id'      => $job?->id,
                        'customer_id'      => $customerId,
                        'service_type'     => 'long_term',
                        'status'           => 'pending',
                        'created_by'       => $userId,
                        'updated_by'       => $userId,
                    ])->id;
                }
            }

            // Gate the now-empty source box out (stops the shipping line's detention).
            $sourceOut = $this->gateContainerOut($source, $job, $transferDate, $userId, 'CARGO_RENTAL_OUT', 'empty');
            $this->closeOpenStorage($source, $transferDate);

            $transfer->update([
                'substitute_yard_storage_id'  => $storage->id,
                'reefer_plug_session_id'      => $reeferSessionId,
                'source_gate_out_movement_id' => $sourceOut->id,
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * Complete a transfer once the cargo is collected: close the substitute box's
     * storage and reefer session, gate the substitute box out (unless the caller
     * keeps it — devan-only), and mark the transfer completed. Same job throughout.
     *
     * @param  array  $data  completion_date, release_box? (default true), notes?
     */
    public function complete(CargoTransfer $transfer, array $data, int $userId): CargoTransfer
    {
        if (! $transfer->isActive()) {
            throw new \RuntimeException('Only an active cargo transfer can be completed.');
        }

        $completionDate = Carbon::parse($data['completion_date']);
        if ($completionDate->lt($transfer->transfer_date)) {
            throw new \RuntimeException(
                'The completion date cannot be before the transfer date (' . $transfer->transfer_date->format('d M Y') . ').'
            );
        }

        $releaseBox = (bool) ($data['release_box'] ?? true);
        $substitute = $transfer->substituteContainer;
        $job        = $transfer->yardJob;

        return DB::transaction(function () use ($transfer, $data, $completionDate, $releaseBox, $substitute, $job, $userId) {
            // Close the substitute box's storage (free_days=0 → every day chargeable).
            $this->closeOpenStorage($substitute, $completionDate);

            // Gate the substitute box out, or keep it (devan-only) as empty stock.
            $subOut = null;
            if ($releaseBox) {
                $subOut = $this->gateContainerOut($substitute, $job, $completionDate, $userId, 'STORAGE_OUT', 'empty');
            } else {
                $substitute->update([
                    'cargo_status'      => 'empty',
                    'status'            => 'in_yard',
                    'status_changed_at' => now(),
                ]);
            }

            // Close the reefer session (mirror gate-out: bill if it was plugged in,
            // else just close a still-pending session with no energy to bill).
            if ($transfer->reefer_plug_session_id) {
                $session = ReeferPlugSession::find($transfer->reefer_plug_session_id);
                if ($session && in_array($session->status, ['pending', 'active'], true)) {
                    $updates = [
                        'gate_out_movement_id' => $subOut?->id,
                        'status'               => 'completed',
                        'updated_by'           => $userId,
                    ];
                    if ($session->status === 'active') {
                        $updates['plug_out_at'] = $completionDate;
                    }
                    $session->update($updates);
                }
            }

            $transfer->update([
                'substitute_gate_out_movement_id' => $subOut?->id,
                'completed_date'                  => $completionDate->toDateString(),
                'status'                          => 'completed',
                'updated_by'                      => $userId,
            ]);

            return $transfer->fresh();
        });
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function guard(GateMovement $sourceMovement, ?Container $source, Container $substitute): void
    {
        if (! $source) {
            throw new \RuntimeException('The source gate movement has no container.');
        }
        if ($sourceMovement->movement_type !== 'in') {
            throw new \RuntimeException('A cargo transfer must start from a gate-in movement.');
        }
        if (! $sourceMovement->yard_job_id) {
            throw new \RuntimeException('This gate-in is not linked to a job, so the transfer cannot be tracked under one job number.');
        }
        if ($substitute->id === $source->id) {
            throw new \RuntimeException('The substitute container must be different from the source box.');
        }
        if (! in_array($substitute->status, ['in_yard', 'available'], true)) {
            throw new \RuntimeException('The substitute container must be present in the yard (in-yard or available).');
        }
        if (CargoTransfer::where('source_gate_movement_id', $sourceMovement->id)->where('status', '!=', 'cancelled')->exists()) {
            throw new \RuntimeException('A cargo transfer already exists for this gate-in.');
        }
        if (CargoTransfer::where('substitute_container_id', $substitute->id)->where('status', 'active')->exists()) {
            throw new \RuntimeException('The substitute container is already holding cargo from another transfer.');
        }
        if ($substitute->activeHire()->exists()) {
            throw new \RuntimeException('The substitute container is on an active hire; choose another box.');
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function resolveDailyRate(?int $customerId, ?int $equipmentTypeId, Carbon $onDate): float
    {
        $header = StorageMasterHeader::where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('valid_from', '<=', $onDate->toDateString())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $onDate->toDateString()))
            ->latest('valid_from')
            ->first();

        if (! $header) {
            return 0.0;
        }

        return (float) ($header->details()->where('equipment_type_id', $equipmentTypeId)->value('storage_rate') ?? 0);
    }

    private function gateContainerOut(Container $container, ?YardJob $job, Carbon $date, int $userId, string $purpose, string $cargoStatus): GateMovement
    {
        $movement = GateMovement::create([
            'container_id'     => $container->id,
            'container_no'     => $container->container_no,
            // From the job, not the box. A yard-owned substitute carries
            // whatever customer it was last gated in under, which has nothing
            // to do with the transfer this movement belongs to.
            'customer_id'      => $job?->customer_id ?? $container->customer_id,
            'yard_job_id'      => $job?->id,
            'movement_type'    => 'out',
            'eir_no'           => app(NumberSequenceService::class)->generate('gate_out'),
            'size'             => $container->size,
            'container_type'   => $container->type_code,
            'cargo_status'     => $cargoStatus,
            'gate_out_purpose' => $purpose,
            'gate_out_time'    => $date,
            'movement_status'  => 'done',
            'created_by'       => $userId,
        ]);

        YardLocation::where('container_id', $container->id)->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        $container->update([
            'status'            => 'released',
            'cargo_status'      => $cargoStatus,
            'status_changed_at' => now(),
            'available_since'   => null,
            'location_zone'     => null,
            'location_row'      => null,
            'location_bay'      => null,
            'location_tier'     => null,
            'gate_out_date'     => $date->toDateString(),
        ]);

        return $movement;
    }

    private function closeOpenStorage(Container $source, Carbon $date): void
    {
        $storage = YardStorage::where('container_id', $source->id)
            ->whereNull('gate_out_date')
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->latest('gate_in_date')
            ->first();

        if (! $storage) {
            return;
        }

        $billingGateIn      = $storage->billing_gate_in_date;
        $daysConsumedBefore = max(0, (int) $billingGateIn->diffInDays($storage->gate_in_date));
        $freeDaysRemaining  = max(0, $storage->free_days - $daysConsumedBefore);
        $totalDays          = max(1, (int) $storage->gate_in_date->diffInDays($date));
        $chargeableDays     = max(0, $totalDays - $freeDaysRemaining);
        $subtotal           = $chargeableDays * (float) $storage->daily_rate;

        $storage->update([
            'gate_out_date'   => $date->toDateString(),
            'total_days'      => $totalDays,
            'chargeable_days' => $chargeableDays,
            'subtotal'        => $subtotal,
            'total_charge'    => $subtotal,
        ]);
    }
}
