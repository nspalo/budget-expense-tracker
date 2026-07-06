<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UserScope implements Scope
{
    /**
     * Apply the user scope to the given Eloquent query builder.
     *
     * Adds a WHERE user_id = ? constraint using the authenticated user's ID.
     * Only applies when a user is authenticated — skips silently in CLI,
     * seeder, or other unauthenticated contexts.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where('user_id', '=', auth()->id());
        }
    }
}
