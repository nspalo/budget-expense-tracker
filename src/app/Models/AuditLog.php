<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Immutable audit log record.
 * No update or delete operations are permitted at the application level.
 *
 * @property int $id
 * @property int $user_id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property AuditEvent $event
 * @property array|null $old_values
 * @property array|null $new_values
 * @property Carbon $created_at
 */
final class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'created_at',
    ];

    protected $casts = [
        'event' => AuditEvent::class,
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): bool {
            throw new RuntimeException('Audit log records are immutable.');
        });

        static::deleting(function (): bool {
            throw new RuntimeException('Audit log records cannot be deleted.');
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Audit log records are immutable.');
        }

        if (! $this->created_at) {
            $this->created_at = Carbon::now('UTC');
        }

        return parent::save($options);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('Audit log records are immutable.');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Audit log records cannot be deleted.');
    }

    public function forceDelete(): ?bool
    {
        throw new RuntimeException('Audit log records cannot be deleted.');
    }
}
