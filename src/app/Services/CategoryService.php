<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategoryType;
use App\Exceptions\DeletionConstraintException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Category;
use App\Models\CategoryGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CategoryService
{
    /**
     * Create a new category for the authenticated user.
     *
     * Validates input, checks group reference, enforces name+type uniqueness,
     * and persists within a transaction. Audit trail fires via model event.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): Category
    {
        $this->validateForCreate($data);

        $name = trim((string) ($data['name'] ?? ''));
        $type = CategoryType::from($data['type']);
        $categoryGroupId = (int) $data['category_group_id'];
        $icon = isset($data['icon']) ? (string) $data['icon'] : null;
        $color = isset($data['color']) ? (string) $data['color'] : null;
        $sortOrder = (int) ($data['sort_order'] ?? 0);

        return DB::transaction(function () use ($name, $type, $categoryGroupId, $icon, $color, $sortOrder): Category {
            return Category::create([
                'name' => $name,
                'type' => $type,
                'category_group_id' => $categoryGroupId,
                'icon' => $icon,
                'color' => $color,
                'sort_order' => $sortOrder,
            ]);
        });
    }

    /**
     * List non-deleted categories for the authenticated user with optional filters.
     *
     * Results are ordered by sort_order ascending, then name ascending.
     *
     * @param  int|null  $groupId  Filter by category group ID
     * @param  CategoryType|null  $type  Filter by category type
     *
     * @throws ValidationException If type string is provided but invalid (handled upstream)
     */
    public function list(?int $groupId = null, ?CategoryType $type = null): Collection
    {
        $query = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($groupId !== null) {
            $query->where('category_group_id', $groupId);
        }

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query->get();
    }

    /**
     * Find a non-deleted category belonging to the authenticated user by ID.
     *
     * @throws EntityNotFoundException
     */
    public function find(int $id): Category
    {
        $category = Category::find($id);

        if ($category === null) {
            throw new EntityNotFoundException('Category');
        }

        return $category;
    }

    /**
     * Update an existing category for the authenticated user.
     *
     * Validates input, rejects type changes (immutable), checks name+type uniqueness
     * excluding self, and skips audit if no auditable fields changed.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws EntityNotFoundException
     */
    public function update(Category $category, array $data): Category
    {
        $this->ensureCategoryAccessible($category);
        $this->validateForUpdate($data, $category);

        $hasChanges = false;

        if (array_key_exists('name', $data)) {
            $trimmedName = trim((string) $data['name']);
            if ($trimmedName !== $category->name) {
                $category->name = $trimmedName;
                $hasChanges = true;
            }
        }

        if (array_key_exists('category_group_id', $data)) {
            $newGroupId = $data['category_group_id'] !== null ? (int) $data['category_group_id'] : null;
            if ($newGroupId !== $category->category_group_id) {
                $category->category_group_id = $newGroupId;
                $hasChanges = true;
            }
        }

        if (array_key_exists('icon', $data)) {
            $newIcon = $data['icon'] !== null ? (string) $data['icon'] : null;
            if ($newIcon !== $category->icon) {
                $category->icon = $newIcon;
                $hasChanges = true;
            }
        }

        if (array_key_exists('color', $data)) {
            $newColor = $data['color'] !== null ? (string) $data['color'] : null;
            if ($newColor !== $category->color) {
                $category->color = $newColor;
                $hasChanges = true;
            }
        }

        if (array_key_exists('sort_order', $data)) {
            $newSortOrder = (int) $data['sort_order'];
            if ($newSortOrder !== $category->sort_order) {
                $category->sort_order = $newSortOrder;
                $hasChanges = true;
            }
        }

        // If no auditable fields changed, return without saving or auditing
        if (! $hasChanges) {
            return $category;
        }

        return DB::transaction(function () use ($category): Category {
            $category->save();

            return $category;
        });
    }

    /**
     * Soft-delete a category for the authenticated user.
     *
     * Checks for non-deleted transactions linked to the category before allowing deletion.
     * Records an audit trail with event "deleted".
     *
     * @throws EntityNotFoundException
     * @throws DeletionConstraintException
     */
    public function delete(Category $category): void
    {
        $this->ensureCategoryAccessible($category);
        $this->checkDeletionConstraints($category);

        DB::transaction(function () use ($category): void {
            $category->delete();
        });
    }

    /**
     * Ensure the category is accessible: exists, belongs to user, and is not soft-deleted.
     *
     * @throws EntityNotFoundException
     */
    private function ensureCategoryAccessible(Category $category): void
    {
        $exists = Category::query()
            ->where('id', $category->id)
            ->exists();

        if (! $exists) {
            throw new EntityNotFoundException('Category');
        }
    }

    /**
     * Check deletion constraints: no active transactions linked to this category.
     *
     * @throws DeletionConstraintException
     */
    private function checkDeletionConstraints(Category $category): void
    {
        try {
            if (! Schema::hasTable('transactions')) {
                return;
            }

            $activeTransactionCount = DB::table('transactions')
                ->where('category_id', $category->id)
                ->whereNull('deleted_at')
                ->count();

            if ($activeTransactionCount > 0) {
                throw new DeletionConstraintException('category', 'transactions', $activeTransactionCount);
            }
        } catch (DeletionConstraintException $e) {
            throw $e;
        } catch (\Throwable) {
            // If the transactions table doesn't exist or query fails, allow delete
            return;
        }
    }

    /**
     * Validate data for category update.
     *
     * @param  array<string, mixed>  $data
     * @param  Category  $category  The category being updated (for uniqueness exclusion).
     *
     * @throws ValidationException
     */
    private function validateForUpdate(array $data, Category $category): void
    {
        $errors = [];

        // Reject type — immutable after creation
        if (array_key_exists('type', $data)) {
            $errors['type'] = ['The type cannot be changed after creation.'];
        }

        // Validate name: optional, 1-50 chars after trim, unique within same type per user (excluding self)
        if (array_key_exists('name', $data)) {
            if (! is_string($data['name'])) {
                $errors['name'] = ['The name must be a string.'];
            } else {
                $trimmedName = trim($data['name']);

                if ($trimmedName === '') {
                    $errors['name'] = ['The name field is required.'];
                } elseif (mb_strlen($trimmedName) > 50) {
                    $errors['name'] = ['The name must not exceed 50 characters.'];
                } else {
                    // Uniqueness check: same name + same type per user, excluding self
                    $exists = Category::query()
                        ->where('name', $trimmedName)
                        ->where('type', $category->type->value)
                        ->where('id', '!=', $category->id)
                        ->exists();

                    if ($exists) {
                        $errors['name'] = ['The name has already been taken for this category type.'];
                    }
                }
            }
        }

        // Validate category_group_id: optional, must reference non-deleted group belonging to user
        if (array_key_exists('category_group_id', $data) && $data['category_group_id'] !== null) {
            $group = CategoryGroup::find($data['category_group_id']);

            if ($group === null) {
                $errors['category_group_id'] = ['The selected category group is invalid.'];
            }
        }

        // Validate icon: optional, max 50 chars
        if (isset($data['icon']) && is_string($data['icon']) && mb_strlen($data['icon']) > 50) {
            $errors['icon'] = ['The icon must not exceed 50 characters.'];
        }

        // Validate color: optional, max 7 chars
        if (isset($data['color']) && is_string($data['color']) && mb_strlen($data['color']) > 7) {
            $errors['color'] = ['The color must not exceed 7 characters.'];
        }

        // Validate sort_order: optional, unsigned integer
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
     * Validate data for category creation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validateForCreate(array $data): void
    {
        $errors = [];

        // Validate name: required, 1-50 chars after trim
        if (! isset($data['name']) || ! is_string($data['name'])) {
            $errors['name'] = ['The name field is required.'];
        } else {
            $trimmedName = trim($data['name']);

            if ($trimmedName === '') {
                $errors['name'] = ['The name field is required.'];
            } elseif (mb_strlen($trimmedName) > 50) {
                $errors['name'] = ['The name must not exceed 50 characters.'];
            }
        }

        // Validate type: required, valid CategoryType
        $resolvedType = null;
        if (! isset($data['type'])) {
            $errors['type'] = ['The type field is required.'];
        } else {
            $resolvedType = CategoryType::tryFrom((string) $data['type']);

            if ($resolvedType === null) {
                $errors['type'] = ['The selected type is invalid.'];
            }
        }

        // Validate category_group_id: required, must reference non-deleted group belonging to user
        if (! isset($data['category_group_id'])) {
            $errors['category_group_id'] = ['The category group field is required.'];
        } else {
            $group = CategoryGroup::find($data['category_group_id']);

            if ($group === null) {
                $errors['category_group_id'] = ['The selected category group is invalid.'];
            }
        }

        // Validate icon: optional, max 50 chars
        if (isset($data['icon']) && is_string($data['icon']) && mb_strlen($data['icon']) > 50) {
            $errors['icon'] = ['The icon must not exceed 50 characters.'];
        }

        // Validate color: optional, max 7 chars
        if (isset($data['color']) && is_string($data['color']) && mb_strlen($data['color']) > 7) {
            $errors['color'] = ['The color must not exceed 7 characters.'];
        }

        // Validate sort_order: optional, unsigned integer
        if (isset($data['sort_order'])) {
            if (! is_int($data['sort_order']) || $data['sort_order'] < 0) {
                $errors['sort_order'] = ['The sort order must be a non-negative integer.'];
            }
        }

        // If we have basic validation errors, throw before checking uniqueness
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // Name uniqueness check: within same type per user (non-deleted)
        $trimmedName = trim((string) $data['name']);
        $exists = Category::query()
            ->where('name', $trimmedName)
            ->where('type', $resolvedType->value)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['The name has already been taken for this category type.'],
            ]);
        }
    }
}
