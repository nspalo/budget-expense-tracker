# Implementation Plan: Core Entities

## Overview

Implement the three foundational domain models (Account, CategoryGroup, Category) with their enums, migrations, service classes, observer, factories, and seeder. Each entity composes existing foundation infrastructure traits and exposes a service layer for CRUD operations with validation, audit logging, and soft-delete lifecycle management.

## Tasks

- [x] 1. Create enums and exception classes
  - [x] 1.1 Create AccountType and CategoryType enums
    - Create `app/Enums/AccountType.php` with backed string enum: BankAccount, DebitCard, EWallet, CreditCard, Cash
    - Create `app/Enums/CategoryType.php` with backed string enum: Expense, Income
    - Both files must declare `strict_types=1` and reside in `App\Enums` namespace
    - _Requirements: 17.1, 17.2, 17.3, 18.1, 18.2, 18.3_

  - [x] 1.2 Create domain exception classes
    - Create `app/Exceptions/ValidationException.php` — domain-level validation exception with structured error bag (field → messages array)
    - Create `app/Exceptions/EntityNotFoundException.php` — uniform "not found" response regardless of reason (non-existent, soft-deleted, or wrong user)
    - Create `app/Exceptions/DeletionConstraintException.php` — thrown when entity has active dependent records blocking deletion
    - _Requirements: 3.6, 4.2, 4.4, 8.5, 9.2, 9.4_

- [x] 2. Create database migrations
  - [x] 2.1 Create accounts migration
    - Define columns: id, user_id (FK → users.id CASCADE), name (string 100), type (string 20), balance_centavos (unsigned bigint, default 0), timestamps, soft deletes
    - Add composite unique index on (user_id, name, deleted_at) — MySQL treats NULLs as distinct for uniqueness enforcement
    - Add composite index on (user_id, type) for filtered listings
    - _Requirements: 1.2, 1.3, 1.4_

  - [x] 2.2 Create category_groups migration
    - Define columns: id, user_id (FK → users.id CASCADE), name (string 50), sort_order (unsigned integer, default 0), timestamps, soft deletes
    - Add composite unique index on (user_id, name, deleted_at)
    - _Requirements: 6.2, 6.3_

  - [x] 2.3 Create categories migration
    - Define columns: id, user_id (FK → users.id CASCADE), category_group_id (unsigned bigint, nullable, FK → category_groups.id SET NULL), name (string 50), type (string 10), icon (string 50, nullable), color (string 7, nullable), sort_order (unsigned integer, default 0), timestamps, soft deletes
    - Add composite unique index on (user_id, name, type, deleted_at)
    - Add composite index on (user_id, category_group_id)
    - _Requirements: 12.2, 12.3, 12.4_

  - [x]* 2.4 Write migration schema test
    - Create `tests/Feature/Database/CoreEntitiesMigrationTest.php`
    - Assert all columns exist with correct types, indexes exist, and foreign key behavior (CASCADE, SET NULL) works correctly
    - _Requirements: 1.2, 1.3, 1.4, 6.2, 6.3, 12.2, 12.3, 12.4_

- [x] 3. Create Eloquent models
  - [x] 3.1 Create Account model
    - Create `app/Models/Account.php` composing BelongsToUser, HasAuditTrail, SoftDeletesWithNullify, HasMonetaryFields traits
    - Declare fillable: name, type, balance_centavos
    - Cast type to AccountType enum, declare monetaryFields() returning ['balance_centavos'], declare getAuditableFields() returning ['name', 'type', 'balance_centavos']
    - Configure nullify on delete for transactions → account_id
    - _Requirements: 1.1, 1.5, 1.6, 1.7, 1.8_

  - [x] 3.2 Create CategoryGroup model
    - Create `app/Models/CategoryGroup.php` composing BelongsToUser, HasAuditTrail, SoftDeletesWithNullify traits
    - Declare fillable: name, sort_order
    - Declare getAuditableFields() returning ['name', 'sort_order']
    - Define hasMany relationship to Category
    - Configure nullify on delete for categories → category_group_id
    - _Requirements: 6.1, 6.4, 6.5, 6.6_

  - [x] 3.3 Create Category model
    - Create `app/Models/Category.php` composing BelongsToUser, HasAuditTrail, SoftDeletesWithNullify traits
    - Declare fillable: name, type, icon, color, sort_order, category_group_id
    - Cast type to CategoryType enum, declare getAuditableFields() returning ['name', 'type', 'icon', 'color', 'sort_order', 'category_group_id']
    - Define belongsTo relationship to CategoryGroup
    - Configure nullify on delete for transactions → category_id
    - _Requirements: 12.1, 12.5, 12.6, 12.7, 12.8_

  - [x]* 3.4 Write model structural unit tests
    - Create `tests/Unit/Models/AccountTest.php` — assert traits, casts, fillable, monetary fields, auditable fields
    - Create `tests/Unit/Models/CategoryGroupTest.php` — assert traits, fillable, auditable fields, relationships
    - Create `tests/Unit/Models/CategoryTest.php` — assert traits, casts, fillable, auditable fields, relationships
    - _Requirements: 1.1, 1.5, 1.6, 1.7, 1.8, 6.1, 6.4, 6.5, 6.6, 12.1, 12.5, 12.6, 12.7, 12.8_

- [x] 4. Checkpoint - Ensure migrations and models work
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement AccountService
  - [x] 5.1 Implement AccountService create and list/find methods
    - Create `app/Services/AccountService.php` as a final class
    - Implement `create(array $data): Account` with validation: name required 1–100 chars after trim, unique among user's non-deleted accounts, valid AccountType, balance_centavos optional integer 0–99,999,999,999 defaulting to 0
    - Implement `list(): Collection` returning user's non-deleted accounts ordered by name ascending
    - Implement `find(int $id): Account` returning user's non-deleted account or throwing EntityNotFoundException
    - Wrap create in DB::transaction, audit trail fires via model event
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 5.1, 5.2, 5.3_

  - [x] 5.2 Implement AccountService update and delete methods
    - Implement `update(Account $account, array $data): Account` with validation: name optional 1–100 chars unique excluding self, type optional valid AccountType, balance_centavos rejected (not directly updatable)
    - Skip audit if no auditable fields changed, do not modify updated_at
    - Implement `delete(Account $account): void` — soft-delete with deletion constraint check (active transactions), audit trail with event "deleted"
    - Throw EntityNotFoundException for non-existent/wrong-user/soft-deleted accounts
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 4.1, 4.2, 4.3, 4.4_

  - [x]* 5.3 Write AccountService unit tests
    - Create `tests/Unit/Services/AccountServiceTest.php`
    - Test specific scenarios: successful create, duplicate name rejection, invalid type rejection, balance out of range, balance update rejection, no-change update skips audit, soft-delete lifecycle, deletion blocked by active transactions, not-found responses
    - _Requirements: 2.1–2.7, 3.1–3.7, 4.1–4.4, 5.1–5.3_

  - [x]* 5.4 Write property tests for Account (Properties 1–8)
    - Create `tests/Unit/Properties/CoreEntities/AccountPropertiesTest.php`
    - **Property 1: Account creation round-trip** — validates Requirements 2.1, 2.6
    - **Property 2: Account name validation rejects invalid names** — validates Requirements 2.4, 3.2
    - **Property 3: Account name uniqueness enforcement** — validates Requirements 2.5, 3.3
    - **Property 4: Account type validation rejects non-enum values** — for any string not matching "bank_account", "debit_card", "e_wallet", "credit_card", or "cash" — validates Requirements 2.3, 3.4
    - **Property 5: Account balance range validation** — validates Requirements 2.7
    - **Property 6: Account no-change update produces no audit** — validates Requirements 3.5
    - **Property 7: Account soft-delete lifecycle** — validates Requirements 4.1, 4.3
    - **Property 8: Account list returns user-scoped, non-deleted, name-ordered results** — validates Requirements 5.1

- [x] 6. Implement CategoryGroupService
  - [x] 6.1 Implement CategoryGroupService create and list/find methods
    - Create `app/Services/CategoryGroupService.php` as a final class
    - Implement `create(array $data): CategoryGroup` with validation: name required 1–50 chars after trim, unique case-insensitive among user's non-deleted groups, sort_order optional unsigned integer defaulting to 0
    - Implement `list(): Collection` returning user's non-deleted groups ordered by sort_order ascending
    - Implement `find(int $id): CategoryGroup` returning user's non-deleted group or throwing EntityNotFoundException
    - Implement `createDefaults(User $user): void` — creates Needs (1), Wants (2), Savings (3), Others (4) with guard clause for existing groups
    - _Requirements: 7.1, 7.2, 7.3, 10.1, 10.2, 11.1, 11.4_

  - [x] 6.2 Implement CategoryGroupService update and delete methods
    - Implement `update(CategoryGroup $group, array $data): CategoryGroup` with validation: name optional 1–50 chars unique case-insensitive excluding self, sort_order optional unsigned integer
    - Skip audit if no auditable fields changed
    - Implement `delete(CategoryGroup $group): void` — soft-delete with deletion constraint check (active categories), audit trail with event "deleted"
    - Throw EntityNotFoundException for non-existent/wrong-user/soft-deleted groups
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 9.1, 9.2, 9.3, 9.4_

  - [x]* 6.3 Write CategoryGroupService unit tests
    - Create `tests/Unit/Services/CategoryGroupServiceTest.php`
    - Test specific scenarios: successful create, case-insensitive duplicate rejection, invalid name rejection, no-change update skips audit, deletion blocked by active categories, createDefaults idempotency
    - _Requirements: 7.1–7.3, 8.1–8.5, 9.1–9.4, 10.1–10.2, 11.1, 11.4_

  - [x]* 6.4 Write property tests for CategoryGroup (Properties 9–15)
    - Create `tests/Unit/Properties/CoreEntities/CategoryGroupPropertiesTest.php`
    - **Property 9: CategoryGroup creation round-trip** — validates Requirements 7.1
    - **Property 10: CategoryGroup name uniqueness (case-insensitive)** — validates Requirements 7.3, 8.3
    - **Property 11: CategoryGroup name validation rejects invalid names** — validates Requirements 7.2, 8.2
    - **Property 12: CategoryGroup no-change update produces no audit** — validates Requirements 8.4
    - **Property 13: CategoryGroup deletion blocked by active categories** — validates Requirements 9.2
    - **Property 14: CategoryGroup soft-delete lifecycle** — validates Requirements 9.1, 9.3
    - **Property 15: CategoryGroup list returns user-scoped, sort_order-ordered results** — validates Requirements 10.1

- [x] 7. Checkpoint - Ensure Account and CategoryGroup services pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implement CategoryService
  - [x] 8.1 Implement CategoryService create and list/find methods
    - Create `app/Services/CategoryService.php` as a final class
    - Implement `create(array $data): Category` with validation: name required 1–50 chars after trim, valid CategoryType, category_group_id required (must reference non-deleted group belonging to user), icon optional max 50, color optional max 7, sort_order optional unsigned int defaulting to 0
    - Enforce name uniqueness within same type per user (not soft-deleted)
    - Implement `list(?int $groupId = null, ?CategoryType $type = null): Collection` with filtering by group and/or type, ordered by sort_order asc then name asc
    - Implement `find(int $id): Category` returning user's non-deleted category or throwing EntityNotFoundException
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7_

  - [x] 8.2 Implement CategoryService update and delete methods
    - Implement `update(Category $category, array $data): Category` with validation: name optional 1–50 chars unique within same type per user (excluding self), type rejected (immutable), category_group_id optional valid reference, icon max 50, color max 7, sort_order optional unsigned int
    - Skip audit if no auditable fields changed
    - Implement `delete(Category $category): void` — soft-delete with deletion constraint check (active transactions), audit trail with event "deleted"
    - Throw EntityNotFoundException for non-existent/wrong-user/soft-deleted categories
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 15.1, 15.2, 15.3, 15.4_

  - [x]* 8.3 Write CategoryService unit tests
    - Create `tests/Unit/Services/CategoryServiceTest.php`
    - Test specific scenarios: successful create, name+type duplicate rejection, invalid group reference, type immutability rejection, icon/color length validation, filtering by group and type, deletion blocked by active transactions
    - _Requirements: 13.1–13.7, 14.1–14.8, 15.1–15.4, 16.1–16.7_

  - [x]* 8.4 Write property tests for Category (Properties 16–21)
    - Create `tests/Unit/Properties/CoreEntities/CategoryPropertiesTest.php`
    - **Property 16: Category creation round-trip** — validates Requirements 13.1, 13.7
    - **Property 17: Category name+type uniqueness per user** — validates Requirements 13.5, 14.3
    - **Property 18: Category name validation rejects invalid names** — validates Requirements 13.4, 14.2
    - **Property 19: Category list filtering preserves correctness** — validates Requirements 16.1, 16.2, 16.3, 16.4
    - **Property 20: Category no-change update produces no audit** — validates Requirements 14.5
    - **Property 21: Category icon/color length validation** — validates Requirements 14.8

- [x] 9. Implement UserObserver for default category groups
  - [x] 9.1 Create UserObserver and register it
    - Create `app/Observers/UserObserver.php` listening to User::created event
    - Call `CategoryGroupService::createDefaults($user)` within the same transaction context
    - Include guard clause: skip if user already has category groups (race condition protection)
    - Register observer in `app/Providers/AppServiceProvider.php` boot method
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

  - [x]* 9.2 Write UserObserver feature test
    - Create `tests/Feature/Observers/UserObserverTest.php`
    - Test that user registration creates four default groups (Needs, Wants, Savings, Others) with correct sort_order
    - Test transaction rollback on failure
    - Test idempotency guard (skip if groups already exist)
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

- [x] 10. Create model factories and database seeder
  - [x] 10.1 Create model factories
    - Create `database/factories/AccountFactory.php` — random name (1–100 chars), random AccountType, random balance_centavos (0–99,999,999,999), auto-associate User
    - Create `database/factories/CategoryGroupFactory.php` — random name (1–50 chars), random sort_order (0–99), auto-associate User
    - Create `database/factories/CategoryFactory.php` — random name (1–50 chars), random CategoryType, valid category_group_id (same user), sort_order (0–99), icon/color null defaults, auto-associate User
    - _Requirements: 19.1, 19.2, 19.3, 19.5_

  - [x] 10.2 Create DatabaseSeeder for demo data
    - Create or update `database/seeders/DatabaseSeeder.php`
    - Create one demo user with five accounts (one per AccountType: BankAccount, DebitCard, EWallet, CreditCard, Cash), four default groups, and at least two categories per group with both expense and income types represented
    - Implement idempotency: skip if demo user already exists
    - _Requirements: 19.4, 19.6_

  - [x]* 10.3 Write property tests for factories (Property 22)
    - Create `tests/Unit/Properties/CoreEntities/FactoryPropertiesTest.php`
    - **Property 22: Factory output validity** — validates Requirements 19.1, 19.2, 19.3
    - Assert generated instances satisfy all model constraints: name lengths, valid enum types, balance ranges, valid foreign keys

- [x] 11. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties using `innmind/black-box`
- Unit tests validate specific examples and edge cases
- All monetary amounts stored as integer centavos (no floats)
- Services validate in-layer (no Form Requests) since API is GraphQL via Lighthouse PHP
- Run tests with `make artisan cmd="test"` or `make artisan cmd="test --filter=CoreEntities"`

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1", "2.2", "2.3"] },
    { "id": 2, "tasks": ["2.4", "3.1", "3.2", "3.3"] },
    { "id": 3, "tasks": ["3.4", "5.1", "6.1"] },
    { "id": 4, "tasks": ["5.2", "5.3", "6.2", "6.3", "8.1"] },
    { "id": 5, "tasks": ["5.4", "6.4", "8.2", "9.1"] },
    { "id": 6, "tasks": ["8.3", "8.4", "9.2", "10.1"] },
    { "id": 7, "tasks": ["10.2", "10.3"] }
  ]
}
```
