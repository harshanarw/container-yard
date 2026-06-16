<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstract base observer that wires Eloquent model events to AuditService.
 * Concrete subclasses only need to implement getModule() and getReference().
 */
abstract class AuditObserver
{
    abstract protected function getModule(): string;

    abstract protected function getReference(Model $model): ?string;

    public function created(Model $model): void
    {
        $ref = $this->getReference($model);
        AuditService::log(
            event: 'created',
            module: $this->getModule(),
            description: $this->describeCreated($model, $ref),
            reference: $ref,
            subject: $model,
            properties: AuditService::snapshot($model),
        );
    }

    public function updated(Model $model): void
    {
        $diff = AuditService::updatedDiff($model);
        if (empty($diff)) {
            return; // only timestamps changed — skip noise
        }

        $ref = $this->getReference($model);
        AuditService::log(
            event: 'updated',
            module: $this->getModule(),
            description: $this->describeUpdated($model, $ref, $diff),
            reference: $ref,
            subject: $model,
            properties: $diff,
        );
    }

    public function deleted(Model $model): void
    {
        $ref = $this->getReference($model);
        AuditService::log(
            event: 'deleted',
            module: $this->getModule(),
            description: $this->describeDeleted($model, $ref),
            reference: $ref,
            subject: $model,
        );
    }

    // ── Description helpers — override in subclass for custom wording ────────

    protected function describeCreated(Model $model, ?string $ref): string
    {
        return $ref
            ? class_basename($model) . " #{$model->getKey()} created — {$ref}"
            : class_basename($model) . " #{$model->getKey()} created";
    }

    protected function describeUpdated(Model $model, ?string $ref, array $diff): string
    {
        $changed = implode(', ', array_keys($diff['old'] ?? []));
        return $ref
            ? class_basename($model) . " #{$model->getKey()} updated [{$changed}] — {$ref}"
            : class_basename($model) . " #{$model->getKey()} updated [{$changed}]";
    }

    protected function describeDeleted(Model $model, ?string $ref): string
    {
        return $ref
            ? class_basename($model) . " #{$model->getKey()} deleted — {$ref}"
            : class_basename($model) . " #{$model->getKey()} deleted";
    }
}
