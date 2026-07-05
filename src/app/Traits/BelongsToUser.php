<?php

declare(strict_types=1);

namespace App\Traits;

use App\Scopes\UserScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToUser
{
    /**
     * Boot the BelongsToUser trait.
     *
     * Registers the UserScope global scope and a creating event
     * that assigns user_id from the authenticated user.
     */
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserScope());

        static::assignUserOnCreating();
    }

    /**
     * Define the relationship to the owning user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Register a creating event listener that assigns user_id from Auth::id().
     *
     * This always overwrites any client-provided user_id value to prevent
     * unauthorized ownership assignment.
     */
    protected static function assignUserOnCreating(): void
    {
        static::creating(function (Model $model): void {
            $model->user_id = Auth::id();
        });
    }
}
