<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

final class UserScope implements Scope
{
    /**
     * Apply the user scope to the given Eloquent query builder.
     *
     * Adds a WHERE user_id = ? constraint using the authenticated user's ID.
     * This applies to SELECT, UPDATE, and DELETE queries on scoped models.
     *
     * @throws AuthenticationException
     */
    public function apply(Builder $builder, Model $model): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new AuthenticationException('No authenticated user');
        }

        $builder->where('user_id', '=', $userId);
    }
}
