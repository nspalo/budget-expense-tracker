<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;

trait SoftDeletesWithNullify
{
    use BaseSoftDeletes;

    /**
     * Define relationship→column mappings for nullification on soft delete.
     *
     * Each key is a relationship method name on the model,
     * and each value is the foreign key column to set NULL on dependent records.
     *
     * Example: ['transactions' => 'account_id']
     *
     * @return array<string, string>
     */
    abstract protected function nullifyOnDelete(): array;

    /**
     * Boot the SoftDeletesWithNullify trait.
     *
     * Registers a deleting event listener that nullifies configured
     * foreign keys on dependent records before the model is soft-deleted.
     */
    public static function bootSoftDeletesWithNullify(): void
    {
        static::deleting(function ($model): void {
            if ($model->isForceDeleting()) {
                return;
            }

            foreach ($model->nullifyOnDelete() as $relationship => $foreignKey) {
                $model->{$relationship}()->update([$foreignKey => null]);
            }
        });
    }

    /**
     * Restore the soft-deleted model.
     *
     * Clears the deleted_at timestamp only. Previously nullified foreign keys
     * on dependent records are NOT re-linked (requirement 10.5).
     */
    public function restoreRecord(): void
    {
        $this->restore();
    }
}
