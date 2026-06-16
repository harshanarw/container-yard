<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /** Fields excluded from property diffs (security / noise). */
    public const EXCLUDED = [
        'password', 'remember_token', 'email_verified_at',
        'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * Write an audit log entry.
     *
     * @param string      $event       created|updated|deleted|approved|rejected|gate-in|gate-out|plug-in|plug-out|temp-log
     * @param string      $module      modules key: yard, surveys, estimates, billing.repair …
     * @param string      $description Human-readable sentence
     * @param string|null $reference   Primary search key — container_no, job_no, invoice_no, etc.
     * @param Model|null  $subject     The Eloquent model being acted on
     * @param array       $properties  Structured diff or snapshot: ['old'=>[…], 'new'=>[…]]
     */
    public static function log(
        string  $event,
        string  $module,
        string  $description,
        ?string $reference  = null,
        ?Model  $subject    = null,
        array   $properties = []
    ): void {
        try {
            $user = Auth::user();

            AuditLog::create([
                'log_name'     => $module,
                'event'        => $event,
                'description'  => $description,
                'reference'    => $reference,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'causer_id'    => $user?->id,
                'causer_name'  => $user?->full_name ?? $user?->name,
                'causer_role'  => $user?->role,
                'properties'   => empty($properties) ? null : $properties,
                'ip_address'   => Request::ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit failures must NEVER break the main application flow
            Log::warning('AuditService failed: ' . $e->getMessage(), [
                'event'  => $event,
                'module' => $module,
            ]);
        }
    }

    /**
     * Build a diff array from a model's getChanges() / getOriginal().
     * Call this inside an `updated` observer method (after save).
     *
     * @return array ['old' => […], 'new' => […]]  or empty array when nothing meaningful changed
     */
    public static function updatedDiff(Model $model): array
    {
        $changes = collect($model->getChanges())
            ->except(self::EXCLUDED)
            ->toArray();

        if (empty($changes)) {
            return [];
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        return ['old' => $old, 'new' => $changes];
    }

    /**
     * Build a snapshot of model attributes for `created` / `deleted` events.
     *
     * @return array ['attributes' => […]]
     */
    public static function snapshot(Model $model): array
    {
        return [
            'attributes' => collect($model->getAttributes())
                ->except(self::EXCLUDED)
                ->toArray(),
        ];
    }
}
