<?php

declare(strict_types=1);

namespace App\Traits;

use App\Scopes\UserScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    /**
     * Boot the BelongsToUser trait.
     *
     * Registers the UserScope global scope and a creating event
     * that auto-fills user_id from the authenticated user when not set.
     */
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserScope());

        static::creating(function (Model $model): void {
            $model->user_id = $model->user_id ?? auth()->id();
        });
    }

    /**
     * Define the relationship to the owning user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
