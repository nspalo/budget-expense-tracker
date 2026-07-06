<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeletionConstraintException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\ValidationException;
use App\Models\CategoryGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CategoryGroupService
{
    /**
     * Create a new category group for the authenticated user.
     *
     * @param  array{name?: string, sort_order?: int}  $data
     *
     * @throws ValidationException If validation fails.
     */
    public function create(array $data): CategoryGroup
    {
        $this->validateForCreate($data);

        $name = trim((string) ($data['name'] ?? ''));
        $sortOrder = $data['sort_order'] ?? 0;

        return DB::transaction(function () use ($name, $sortOrder): CategoryGroup {
            return CategoryGroup::create([
                'name' => $name,
                'sort_order' => $sortOrder,
            ]);
        });
    }

    /**
     * List all non-deleted category groups for the authenticated user.
     *
     * Returns groups ordered by sort_order ascending.
     */
    public function list(): Collection
    {
        return CategoryGroup::query()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Find a non-deleted category group by ID for the authenticated user.
     *
     * @throws EntityNotFoundException If the group does not exist, is soft-deleted, or belongs to another user.
     */
    public function find(int $id): CategoryGroup
    {
        $group = CategoryGroup::find($id);

        if ($group === null) {
            throw new EntityNotFoundException('CategoryGroup');
        }

        return $group;
    }

    /**
     * Create default category groups for a newly registered user.
     *
     * Creates Needs (1), Wants (2), Savings (3), Others (4).
     * Guard clause: skips if user already has category groups (race condition protection).
     *
     * This method receives a User explicitly since auth may not be set during registration.
     */
    public function createDefaults(User $user): void
    {
        // Guard clause: skip if user already has category groups
        $existingCount = CategoryGroup::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->count();

        if ($existingCount > 0) {
            return;
        }

        $defaults = [
            ['name' => 'Needs', 'sort_order' => 1],
            ['name' => 'Wants', 'sort_order' => 2],
            ['name' => 'Savings', 'sort_order' => 3],
            ['name' => 'Others', 'sort_order' => 4],
        ];

        foreach ($defaults as $default) {
            $group = new CategoryGroup();
            $group->user_id = $user->id;
            $group->name = $default['name'];
            $group->sort_order = $default['sort_order'];
            $group->save();
        }
    }

    /**
     * Update a category group's name or sort_order.
     *
     * Validates input, checks case-insensitive uniqueness (excluding self),
     * skips audit if no auditable fields changed.
     *
     * @param  array{name?: string, sort_order?: int}  $data
     *
     * @throws ValidationException If validation fails.
     * @throws EntityNotFoundException If group is not found, wrong user, or soft-deleted.
     */
    public function update(CategoryGroup $group, array $data): CategoryGroup
    {
        $this->ensureGroupAccessible($group);
        $this->validateForUpdate($data, $group);

        $hasChanges = false;

        // Determine changes
        if (isset($data['name'])) {
            $trimmedName = trim($data['name']);
            if ($trimmedName !== $group->name) {
                $group->name = $trimmedName;
                $hasChanges = true;
            }
        }

        if (array_key_exists('sort_order', $data)) {
            $sortOrder = $data['sort_order'] ?? $group->sort_order;
            if ($sortOrder !== $group->sort_order) {
                $group->sort_order = $sortOrder;
                $hasChanges = true;
            }
        }

        // If no auditable fields changed, return without saving or auditing
        if (! $hasChanges) {
            return $group;
        }

        return DB::transaction(function () use ($group): CategoryGroup {
            $group->save();

            return $group;
        });
    }

    /**
     * Soft-delete a category group.
     *
     * Checks for non-deleted categories linked to the group before allowing deletion.
     * Records an audit trail with event "deleted" and the group's field values.
     *
     * @throws EntityNotFoundException If group is not found, wrong user, or soft-deleted.
     * @throws DeletionConstraintException If group has active categories.
     */
    public function delete(CategoryGroup $group): void
    {
        $this->ensureGroupAccessible($group);

        // Check for non-deleted categories linked to this group
        $activeCategoryCount = $group->categories()->whereNull('deleted_at')->count();

        if ($activeCategoryCount > 0) {
            throw new DeletionConstraintException('category group', 'categories', $activeCategoryCount);
        }

        DB::transaction(function () use ($group): void {
            $group->delete();
        });
    }

    /**
     * Ensure the group is accessible: exists, belongs to user, and is not soft-deleted.
     *
     * @throws EntityNotFoundException
     */
    private function ensureGroupAccessible(CategoryGroup $group): void
    {
        // The BelongsToUser global scope already filters by authenticated user.
        // If we got here with a model that's soft-deleted or doesn't belong to user,
        // we need to verify by re-querying.
        $exists = CategoryGroup::query()
            ->where('id', $group->id)
            ->exists();

        if (! $exists) {
            throw new EntityNotFoundException('CategoryGroup');
        }
    }

    /**
     * Validate data for category group creation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validateForCreate(array $data): void
    {
        $errors = [];

        // Name validation: required, 1-50 chars after trim
        if (! isset($data['name']) || ! is_string($data['name'])) {
            $errors['name'] = ['The name field is required.'];
        } else {
            $trimmedName = trim($data['name']);

            if ($trimmedName === '') {
                $errors['name'] = ['The name field is required.'];
            } elseif (mb_strlen($trimmedName) > 50) {
                $errors['name'] = ['The name must not exceed 50 characters.'];
            } else {
                // Case-insensitive uniqueness check among user's non-deleted groups
                $exists = CategoryGroup::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmedName)])
                    ->exists();

                if ($exists) {
                    $errors['name'] = ['The name has already been taken.'];
                }
            }
        }

        // sort_order validation: optional, unsigned integer
        if (isset($data['sort_order'])) {
            if (! is_int($data['sort_order']) || $data['sort_order'] < 0) {
                $errors['sort_order'] = ['The sort order must be a non-negative integer.'];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate data for category group update.
     *
     * @param  array<string, mixed>  $data
     * @param  CategoryGroup  $group  The group being updated (for uniqueness exclusion).
     *
     * @throws ValidationException
     */
    private function validateForUpdate(array $data, CategoryGroup $group): void
    {
        $errors = [];

        // Name validation: optional, 1-50 chars after trim, unique case-insensitive excluding self
        if (isset($data['name'])) {
            if (! is_string($data['name'])) {
                $errors['name'] = ['The name must be a string.'];
            } else {
                $trimmedName = trim($data['name']);

                if ($trimmedName === '') {
                    $errors['name'] = ['The name field is required.'];
                } elseif (mb_strlen($trimmedName) > 50) {
                    $errors['name'] = ['The name must not exceed 50 characters.'];
                } else {
                    // Case-insensitive uniqueness check excluding self
                    $exists = CategoryGroup::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmedName)])
                        ->where('id', '!=', $group->id)
                        ->exists();

                    if ($exists) {
                        $errors['name'] = ['The name has already been taken.'];
                    }
                }
            }
        }

        // sort_order validation: optional, unsigned integer
        if (isset($data['sort_order'])) {
            if (! is_int($data['sort_order']) || $data['sort_order'] < 0) {
                $errors['sort_order'] = ['The sort order must be a non-negative integer.'];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
