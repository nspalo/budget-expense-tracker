# Implementation Plan: Foundation Infrastructure

## Overview

Implements the four cross-cutting infrastructure concerns for the Budget & Expense Tracker: Money value object with integer centavo arithmetic, soft deletion with foreign key nullification, an immutable audit trail with transactional atomicity, and user-scoped data isolation. All components are built as composable traits, value objects, and services using PHP 8.4, Laravel 12.x, and MySQL 8.0.

## Tasks

- [x] 1. Set up core infrastructure files and enums
  - [x] 1.1 Create the AuditEvent enum and AuditFailedException
    - Create `src/app/Enums/AuditEvent.php` with cases: Created, Updated, Deleted (string-backed)
    - Create `src/app/Exceptions/AuditFailedException.php` extending RuntimeException
    - _Requirements: 11.1, 11.2, 11.3, 12.2_

  - [x] 1.2 Create the audit_logs migration
    - Create migration in `src/database/migrations/` with columns: id, user_id, auditable_type, auditable_id, event (ENUM), old_values (nullable JSON), new_values (nullable JSON), created_at (timestamp)
    - Add foreign key on user_id referencing users table with RESTRICT on delete
    - Add composite index on (auditable_type, auditable_id)
    - Add composite index on (user_id, created_at)
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7_

- [x] 2. Implement Money value object
  - [x] 2.1 Create the Money value object class
    - Create `src/app/ValueObjects/Money.php` as `final readonly class`
    - Implement constructor with range validation (0 to 99,999,999,999)
    - Implement `fromCentavos()` and `fromPesos()` static factories
    - Implement `toCentavos()` and `toPesos()` accessors
    - Implement `add()`, `subtract()`, `multiply()` arithmetic methods returning new instances
    - Implement static `divide()` for remainder distribution (1 to 1,000 parts)
    - Implement `format()` returning "₱X,XXX.XX" with thousands separators
    - Implement `equals()`, `greaterThan()`, `isZero()` comparison methods
    - Throw InvalidArgumentException for: out-of-range values, negative results, more than 2 decimal places in fromPesos, parts < 1 or > 1000 in divide
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10_

  - [ ]* 2.2 Write property tests for Money value object
    - **Property 1: Division Totality** — For random total (0–99,999,999,999) and parts (1–60), array_sum(Money::divide(total, parts)) === total
    - **Property 2: Monetary Range Invariant** — All constructed Money instances have centavos in [0, 99,999,999,999]
    - **Property 3: Peso-Centavo Conversion Round-Trip** — For valid centavo values, fromCentavos(x)->toPesos() piped through fromPesos() === x
    - **Property 4: Arithmetic Integrity** — a.add(b).toCentavos() === a.toCentavos() + b.toCentavos()
    - **Property 5: Invalid Peso Rejection** — fromPesos with >2 decimal places always throws
    - **Property 6: Display Format Consistency** — format() always matches "₱X,XXX.XX" pattern
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 9.7, 9.8, 9.10**

  - [ ]* 2.3 Write unit tests for Money value object
    - Test fromCentavos with boundary values (0, 99999999999)
    - Test fromPesos with valid conversions (1500.00 → 150000)
    - Test fromPesos rejects 3+ decimal places
    - Test add/subtract/multiply produce correct results
    - Test divide edge cases: 1 centavo / 3 parts, 0 / 5 parts, even division
    - Test format output for various amounts
    - Test InvalidArgumentException for out-of-range values
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9_

- [x] 3. Implement HasMonetaryFields trait
  - [x] 3.1 Create the HasMonetaryFields trait
    - Create `src/app/Traits/HasMonetaryFields.php`
    - Define abstract `monetaryFields(): array` method for model declaration
    - Implement `initializeHasMonetaryFields()` to register attribute accessors/mutators
    - On get: return Money::fromCentavos() for non-null int values, null for NULL
    - On set: accept Money instance (persist centavos), int in valid range (persist directly), null (persist null)
    - Throw InvalidArgumentException for negative/overflow integers and unsupported types
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7_

  - [ ]* 3.2 Write unit tests for HasMonetaryFields trait
    - Test reading a non-null integer returns Money value object
    - Test reading NULL returns null
    - Test writing a Money object persists centavo integer
    - Test writing a valid integer persists directly
    - Test writing out-of-range integer throws InvalidArgumentException
    - Test writing unsupported type (string, float) throws InvalidArgumentException
    - **Property 20: Monetary Field Casting Round-Trip** — read → write → read produces same value
    - **Validates: Requirements 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7**

- [x] 4. Checkpoint - Ensure all monetary tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement UserScope and BelongsToUser trait
  - [x] 5.1 Create the UserScope global scope
    - Create `src/app/Scopes/UserScope.php` implementing `Illuminate\Database\Eloquent\Scope`
    - In `apply()`: resolve Auth::id(), throw AuthenticationException if null, add WHERE user_id = ? constraint
    - Scope applies to SELECT, UPDATE, DELETE queries
    - _Requirements: 13.1, 13.4, 13.5, 13.6_

  - [x] 5.2 Create the BelongsToUser trait
    - Create `src/app/Traits/BelongsToUser.php`
    - In `bootBelongsToUser()`: register UserScope via `static::addGlobalScope()`
    - In `creating` event: assign user_id from Auth::id(), overwrite any client-provided value
    - Add `user()` BelongsTo relationship method
    - _Requirements: 13.1, 13.2, 13.3, 13.5, 13.7_

  - [ ]* 5.3 Write feature tests for UserScope and BelongsToUser
    - Test that queries automatically include WHERE user_id = authenticated_user constraint
    - Test that accessing another user's record returns null (not forbidden)
    - Test that user_id is always assigned from Auth::id() on creation, ignoring input
    - Test that AuthenticationException is thrown when Auth::id() is null
    - Test that scope applies to UPDATE and DELETE queries
    - Test that eager-loaded relationships also apply UserScope independently
    - **Property 17: User Query Isolation** — queries always constrained to authenticated user
    - **Property 18: Server-Side User Assignment** — persisted user_id always equals Auth::id()
    - **Property 19: Cross-User Access Returns Not Found** — other user's records return null
    - **Validates: Requirements 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7**

- [x] 6. Implement SoftDeletesWithNullify trait
  - [x] 6.1 Create the SoftDeletesWithNullify trait
    - Create `src/app/Traits/SoftDeletesWithNullify.php`
    - Use Laravel's `SoftDeletes` trait internally
    - Define abstract `nullifyOnDelete(): array` returning relationship→column mappings
    - Override `performDeleteOnModel()` or hook into `deleting` event to nullify configured foreign keys on dependent records
    - On restore: clear deleted_at only (do NOT re-link nullified foreign keys)
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [ ]* 6.2 Write feature tests for SoftDeletesWithNullify trait
    - Test soft delete sets deleted_at without modifying other fields
    - Test dependent records have foreign key set to NULL after parent soft-delete
    - Test standard queries exclude soft-deleted records
    - Test restore clears deleted_at and makes record visible
    - Test restore does NOT re-link previously nullified foreign keys
    - **Property 7: Soft Delete Field Preservation** — only deleted_at changes on soft delete
    - **Property 8: Soft Delete Query Exclusion** — soft-deleted records excluded from standard queries
    - **Property 9: Soft Delete Restore Visibility Round-Trip** — restore makes record visible again
    - **Property 10: Nullify on Delete** — configured FK columns set to NULL on dependents
    - **Validates: Requirements 10.1, 10.2, 10.3, 10.4, 10.5**

- [x] 7. Checkpoint - Ensure all scope and soft-delete tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implement AuditLog model and AuditService
  - [x] 8.1 Create the AuditLog model
    - Create `src/app/Models/AuditLog.php` as `final class`
    - Set `$timestamps = false`, define `$fillable` and `$casts` (old_values/new_values as array, created_at as datetime)
    - Override `save()` to auto-set created_at in UTC if creating
    - Override `update()` and `delete()` to throw ImmutableRecordException (or RuntimeException) preventing modification
    - Prevent mass-update and force-delete at application level
    - _Requirements: 11.4, 11.5, 11.8, 12.1, 12.2, 12.3, 12.4_

  - [x] 8.2 Create the AuditService
    - Create `src/app/Services/AuditService.php` as `final class`
    - Implement `logCreation(Model $model)`: write audit entry with event=created, new_values = auditable attributes, old_values = null
    - Implement `logUpdate(Model $model, array $oldValues, array $newValues)`: write entry with event=updated, both old/new values
    - Implement `logDeletion(Model $model)`: write entry with event=deleted, old_values = auditable attributes, new_values = null
    - Implement `getHistory(string $entityType, int $entityId)`: return audit entries ordered newest-first, limited to 100
    - All log methods must persist within the current transaction; throw AuditFailedException on failure
    - Set created_at to `now()->utc()` with second precision
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.6, 11.7, 11.8_

  - [x] 8.3 Create the HasAuditTrail trait
    - Create `src/app/Traits/HasAuditTrail.php`
    - In `bootHasAuditTrail()`: register `created`, `updated`, `deleting` model event listeners
    - Resolve AuditService from the container and delegate to appropriate log method
    - Implement `getAuditableFields()`: return model's `$auditableFields` property if defined, else all attributes excluding timestamps and soft-delete columns
    - On update: compare old vs new for auditable fields only; skip audit if no auditable fields changed
    - Capture original values before mutation via `getOriginal()`
    - _Requirements: 11.1, 11.2, 11.3, 11.9, 11.10, 11.11_

  - [ ]* 8.4 Write feature tests for AuditService and HasAuditTrail
    - Test that creating a model produces audit entry with event=created and correct new_values
    - Test that updating a model produces audit entry with old_values and new_values
    - Test that soft-deleting a model produces audit entry with event=deleted
    - Test that updating non-auditable fields does NOT create an audit entry
    - Test that AuditLog cannot be updated or deleted (immutability)
    - Test that audit failure rolls back the entire transaction
    - Test getHistory returns entries newest-first, max 100
    - Test that user_id and created_at (UTC) are correctly captured
    - **Property 11: Audit Completeness and Structure** — every mutation produces one correct audit entry
    - **Property 12: Audit Value Nullability by Event Type** — created→old=null, deleted→new=null
    - **Property 13: Audit Immutability** — update/delete on AuditLog always throws
    - **Property 14: Audit Atomicity** — audit failure rolls back mutation
    - **Property 15: Auditable Field Filtering** — non-auditable field changes produce no audit entry
    - **Property 16: Audit History Ordering** — history returned newest-first
    - **Validates: Requirements 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 11.11**

- [x] 9. Checkpoint - Ensure all audit tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Integration wiring and final validation
  - [x] 10.1 Create an example financial model composing all traits
    - Create a test-only model (e.g., `src/tests/Stubs/FinancialModelStub.php`) that uses BelongsToUser, HasAuditTrail, SoftDeletesWithNullify, and HasMonetaryFields together
    - Create a test migration for the stub model table
    - Verify all traits initialize correctly without conflicts
    - _Requirements: 9.1, 10.1, 11.1, 13.1, 14.1_

  - [ ]* 10.2 Write integration tests for composed traits
    - Test full lifecycle: create (user scoped, audited, monetary) → update (audit captures changes) → soft-delete (nullifies dependents, audited) → restore
    - Test that UserScope still applies when querying with trashed records
    - Test that audit log captures monetary field changes correctly
    - Test that audit failure during soft-delete rolls back both delete and nullification
    - **Validates: Requirements 9.1, 10.1, 10.4, 11.1, 11.7, 13.1, 13.6, 14.1**

- [x] 11. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- All code uses `declare(strict_types=1)` and PHP 8.4 features (readonly, enums)
- Run tests via `make artisan cmd="test"` or target specific files with `make artisan cmd="test --filter=MoneyTest"`
- The stub model in task 10.1 is test-only; actual financial models (Account, Transaction, Category) are implemented in their own feature specs

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1", "5.1"] },
    { "id": 2, "tasks": ["2.2", "2.3", "3.1", "5.2"] },
    { "id": 3, "tasks": ["3.2", "5.3", "6.1"] },
    { "id": 4, "tasks": ["6.2", "8.1"] },
    { "id": 5, "tasks": ["8.2"] },
    { "id": 6, "tasks": ["8.3"] },
    { "id": 7, "tasks": ["8.4"] },
    { "id": 8, "tasks": ["10.1"] },
    { "id": 9, "tasks": ["10.2"] }
  ]
}
```
