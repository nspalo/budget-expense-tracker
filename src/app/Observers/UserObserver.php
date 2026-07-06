<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\CategoryGroupService;

class UserObserver
{
    public function __construct(
        private readonly CategoryGroupService $categoryGroupService,
    ) {}

    /**
     * Handle the User "created" event.
     *
     * Creates default category groups for the newly registered user.
     * Relies on the outer transaction from the registration flow — any exception
     * thrown here will propagate and cause that transaction to roll back.
     *
     * Guard clause in CategoryGroupService::createDefaults handles race conditions
     * (skips if user already has category groups).
     */
    public function created(User $user): void
    {
        $this->categoryGroupService->createDefaults($user);
    }
}
