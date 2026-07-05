<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

trait HasAuditTrail
{
    public static function bootHasAuditTrail(): void
    {
        static::created(function (Model $model): void {
            $auditService = app(AuditService::class);
            $auditService->logCreation($model);
        });

        static::updated(function (Model $model): void {
            /** @var Model&HasAuditTrail $model */
            $auditableFields = $model->getAuditableFields();
            $oldValues = [];
            $newValues = [];

            foreach ($auditableFields as $field) {
                $originalValue = $model->getOriginal($field);
                $currentValue = $model->getAttribute($field);

                if ($originalValue !== $currentValue) {
                    $oldValues[$field] = $originalValue;
                    $newValues[$field] = $currentValue;
                }
            }

            if (empty($oldValues)) {
                return;
            }

            $auditService = app(AuditService::class);
            $auditService->logUpdate($model, $oldValues, $newValues);
        });

        static::deleting(function (Model $model): void {
            $auditService = app(AuditService::class);
            $auditService->logDeletion($model);
        });
    }

    protected function getAuditableFields(): array
    {
        if (property_exists($this, 'auditableFields') && !empty($this->auditableFields)) {
            return $this->auditableFields;
        }

        $excludedFields = ['created_at', 'updated_at', 'deleted_at'];

        return array_values(array_diff(array_keys($this->getAttributes()), $excludedFields));
    }

    protected function getOriginalAuditValues(): array
    {
        $auditableFields = $this->getAuditableFields();
        $values = [];

        foreach ($auditableFields as $field) {
            $values[$field] = $this->getOriginal($field);
        }

        return $values;
    }
}
