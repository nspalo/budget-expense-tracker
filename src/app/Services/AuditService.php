<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEvent;
use App\Exceptions\AuditFailedException;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class AuditService
{
    /**
     * Log a creation event for a model.
     *
     * Writes an audit entry with event=created, new_values = auditable attributes, old_values = null.
     * Must be called within an active database transaction.
     *
     * @throws AuditFailedException If the audit record cannot be persisted.
     */
    public function logCreation(Model $model): void
    {
        $this->persistAuditEntry(
            model: $model,
            event: AuditEvent::Created,
            oldValues: null,
            newValues: $this->getAuditableAttributes($model),
        );
    }

    /**
     * Log an update event for a model.
     *
     * Writes an audit entry with event=updated, both old and new values.
     * Must be called within an active database transaction.
     *
     * @param  array<string, mixed>  $oldValues  Previous values of changed auditable fields.
     * @param  array<string, mixed>  $newValues  New values of changed auditable fields.
     *
     * @throws AuditFailedException If the audit record cannot be persisted.
     */
    public function logUpdate(Model $model, array $oldValues, array $newValues): void
    {
        $this->persistAuditEntry(
            model: $model,
            event: AuditEvent::Updated,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }

    /**
     * Log a deletion event for a model.
     *
     * Writes an audit entry with event=deleted, old_values = auditable attributes, new_values = null.
     * Must be called within an active database transaction.
     *
     * @throws AuditFailedException If the audit record cannot be persisted.
     */
    public function logDeletion(Model $model): void
    {
        $this->persistAuditEntry(
            model: $model,
            event: AuditEvent::Deleted,
            oldValues: $this->getAuditableAttributes($model),
            newValues: null,
        );
    }

    /**
     * Retrieve audit history for a specific entity.
     *
     * Returns audit entries ordered newest-first, limited to 100 entries.
     */
    public function getHistory(string $entityType, int $entityId): Collection
    {
        return AuditLog::query()
            ->where('auditable_type', $entityType)
            ->where('auditable_id', $entityId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * Persist an audit log entry within the current transaction.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     *
     * @throws AuditFailedException
     */
    private function persistAuditEntry(
        Model $model,
        AuditEvent $event,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'event' => $event,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'created_at' => Carbon::now('UTC')->startOfSecond(),
            ]);
        } catch (\Throwable $e) {
            throw new AuditFailedException(
                message: 'Failed to persist audit log entry.',
                previous: $e,
            );
        }
    }

    /**
     * Get the auditable attributes from a model.
     *
     * If the model defines an $auditableFields property, only those fields are returned.
     * Otherwise, all attributes excluding timestamps and soft-delete columns are returned.
     *
     * @return array<string, mixed>
     */
    private function getAuditableAttributes(Model $model): array
    {
        if (property_exists($model, 'auditableFields') && ! empty($model->auditableFields)) {
            $fields = $model->auditableFields;
        } else {
            $excludedFields = ['created_at', 'updated_at', 'deleted_at'];
            $fields = array_diff(array_keys($model->getAttributes()), $excludedFields);
        }

        $attributes = [];
        foreach ($fields as $field) {
            if ($model->offsetExists($field) || array_key_exists($field, $model->getAttributes())) {
                $attributes[$field] = $model->getAttribute($field);
            }
        }

        return $attributes;
    }
}
