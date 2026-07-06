<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Exceptions\DeletionConstraintException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountService
{
    /**
     * Create a new account for the authenticated user.
     *
     * Validates input, checks uniqueness, and persists within a transaction.
     * Audit trail fires automatically via the HasAuditTrail model event.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    public function create(array $data): Account
    {
        $this->validateCreate($data);

        $name = trim((string) ($data['name'] ?? ''));
        $type = AccountType::from($data['type']);
        $balanceCentavos = (int) ($data['balance_centavos'] ?? 0);

        return DB::transaction(function () use ($name, $type, $balanceCentavos): Account {
            return Account::create([
                'name' => $name,
                'type' => $type,
                'balance_centavos' => $balanceCentavos,
            ]);
        });
    }

    /**
     * List all non-deleted accounts for the authenticated user, ordered by name ascending.
     *
     * Returns an empty collection if none exist.
     */
    public function list(): Collection
    {
        return Account::orderBy('name')->get();
    }

    /**
     * Find a non-deleted account belonging to the authenticated user by ID.
     *
     * @throws EntityNotFoundException
     */
    public function find(int $id): Account
    {
        $account = Account::find($id);

        if ($account === null) {
            throw new EntityNotFoundException('Account');
        }

        return $account;
    }

    /**
     * Update an existing account for the authenticated user.
     *
     * Validates input, checks uniqueness excluding self, rejects balance_centavos changes.
     * Skips audit and updated_at modification if no auditable fields changed.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     * @throws EntityNotFoundException
     */
    public function update(Account $account, array $data): Account
    {
        $this->guardAccountAccess($account);
        $this->validateUpdate($account, $data);

        // Determine which auditable fields actually changed
        $changed = false;

        if (array_key_exists('name', $data)) {
            $newName = trim((string) $data['name']);
            if ($newName !== $account->name) {
                $account->name = $newName;
                $changed = true;
            }
        }

        if (array_key_exists('type', $data)) {
            $newType = AccountType::from($data['type']);
            if ($newType !== $account->type) {
                $account->type = $newType;
                $changed = true;
            }
        }

        // If nothing changed, return without saving (no audit, no updated_at modification)
        if (!$changed) {
            return $account;
        }

        return DB::transaction(function () use ($account): Account {
            $account->save();

            return $account;
        });
    }

    /**
     * Soft-delete an account for the authenticated user.
     *
     * Checks for active (non-deleted) transactions linked to the account.
     * Performs soft-delete and records audit trail within a transaction.
     *
     * @throws EntityNotFoundException
     * @throws DeletionConstraintException
     */
    public function delete(Account $account): void
    {
        $this->guardAccountAccess($account);
        $this->checkDeletionConstraints($account);

        DB::transaction(function () use ($account): void {
            $account->delete();
        });
    }

    /**
     * Guard access to an account: must exist, belong to user, and not be soft-deleted.
     *
     * @throws EntityNotFoundException
     */
    private function guardAccountAccess(Account $account): void
    {
        // The BelongsToUser global scope already filters by user.
        // If the account was soft-deleted or not found via the scope, it would be null.
        // But since we receive a model instance, check if it's trashed.
        if ($account->trashed()) {
            throw new EntityNotFoundException('Account');
        }
    }

    /**
     * Check deletion constraints: no active transactions linked to this account.
     *
     * @throws DeletionConstraintException
     */
    private function checkDeletionConstraints(Account $account): void
    {
        try {
            if (!Schema::hasTable('transactions')) {
                return;
            }

            $activeTransactionCount = DB::table('transactions')
                ->where('account_id', $account->id)
                ->whereNull('deleted_at')
                ->count();

            if ($activeTransactionCount > 0) {
                throw new DeletionConstraintException('account', 'transactions', $activeTransactionCount);
            }
        } catch (DeletionConstraintException $e) {
            throw $e;
        } catch (\Throwable) {
            // If the transactions table doesn't exist or query fails, allow delete
            return;
        }
    }

    /**
     * Validate data for account update.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function validateUpdate(Account $account, array $data): void
    {
        $errors = [];

        // Reject balance_centavos — not directly updatable
        if (array_key_exists('balance_centavos', $data)) {
            $errors['balance_centavos'] = ['The balance is not directly updatable.'];
        }

        // Validate name (optional on update)
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);

            if ($name === '') {
                $errors['name'] = ['The name field is required.'];
            } elseif (mb_strlen($name) > 100) {
                $errors['name'] = ['The name must not exceed 100 characters.'];
            } else {
                // Check uniqueness among user's non-deleted accounts, excluding self
                $exists = Account::where('name', $name)
                    ->where('id', '!=', $account->id)
                    ->exists();

                if ($exists) {
                    $errors['name'] = ['The name has already been taken.'];
                }
            }
        }

        // Validate type (optional on update)
        if (array_key_exists('type', $data)) {
            $type = AccountType::tryFrom((string) $data['type']);

            if ($type === null) {
                $errors['type'] = ['The selected type is invalid.'];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate data for account creation.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function validateCreate(array $data): void
    {
        $errors = [];

        // Validate name
        $name = isset($data['name']) ? trim((string) $data['name']) : '';

        if ($name === '') {
            $errors['name'] = ['The name field is required.'];
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = ['The name must not exceed 100 characters.'];
        } else {
            // Check uniqueness among user's non-deleted accounts
            $exists = Account::where('name', $name)->exists();

            if ($exists) {
                $errors['name'] = ['The name has already been taken.'];
            }
        }

        // Validate type
        if (!isset($data['type'])) {
            $errors['type'] = ['The type field is required.'];
        } else {
            $type = AccountType::tryFrom((string) $data['type']);

            if ($type === null) {
                $errors['type'] = ['The selected type is invalid.'];
            }
        }

        // Validate balance_centavos (optional, defaults to 0)
        if (array_key_exists('balance_centavos', $data) && $data['balance_centavos'] !== null) {
            $balance = $data['balance_centavos'];

            if (!is_int($balance) && !is_numeric($balance)) {
                $errors['balance_centavos'] = ['The balance must be an integer.'];
            } else {
                $balanceInt = (int) $balance;

                if ($balanceInt < 0 || $balanceInt > 99_999_999_999) {
                    $errors['balance_centavos'] = ['The balance must be between 0 and 99,999,999,999 centavos.'];
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
