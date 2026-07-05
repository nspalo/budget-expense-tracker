# Requirements Document

## Introduction

Foundation Infrastructure provides four cross-cutting concerns that every financial model in the Budget & Expense Tracker depends on: monetary precision via integer centavo arithmetic, soft deletion with referential integrity, an immutable audit trail with transactional atomicity, and user-scoped data isolation via Eloquent global scopes. These concerns are implemented as composable traits, value objects, and services following SOLID principles.

## Glossary

- **Money**: A value object that encapsulates monetary amounts as integer centavos, providing arithmetic operations and conversion between pesos and centavos.
- **Centavo**: The smallest unit of Philippine Peso (₱). 1 peso = 100 centavos. All monetary amounts are stored and computed in centavos.
- **HasMonetaryFields_Trait**: An Eloquent trait that casts monetary database columns to and from the Money value object.
- **UserScope**: An Eloquent global scope that automatically filters all queries to the authenticated user's records.
- **BelongsToUser_Trait**: An Eloquent trait that registers the UserScope and auto-assigns user_id on model creation.
- **AuditService**: A service that writes immutable audit log entries within the same database transaction as the originating mutation.
- **HasAuditTrail_Trait**: An Eloquent trait that hooks into model events and delegates audit logging to AuditService.
- **SoftDeletesWithNullify_Trait**: An Eloquent trait that soft-deletes records and nullifies configured foreign keys on dependent records.
- **AuditLog**: An immutable database record capturing who changed what and when on financial entities.
- **Financial_Model**: Any Eloquent model representing financial data (accounts, transactions, categories) that composes the foundation traits.
- **Remainder_Distribution**: An algorithm that divides a total centavo amount into N parts where the sum equals the original total exactly.

## Requirements

### Requirement 9: Money Value Object & Integer Arithmetic

**User Story:** As a developer, I want all monetary values represented as integer centavos with a dedicated value object, so that float rounding errors never corrupt financial calculations.

#### Acceptance Criteria

1. THE Money value object SHALL store all monetary amounts as integers representing centavos within the range 0 to 99,999,999,999.
2. WHEN arithmetic operations (add, subtract) are performed on Money instances, THE Money value object SHALL compute results using integer-only arithmetic without intermediate float operations and return a new Money instance.
3. WHEN a multiply operation is performed on a Money instance with an integer scalar, THE Money value object SHALL compute the result using integer-only arithmetic and return a new Money instance.
4. WHEN a peso value is received at the API boundary, THE Money value object SHALL convert it to centavos by multiplying by 100 and truncating any fractional remainder after the multiplication.
5. WHEN a total centavo amount is divided into N parts where N is between 1 and 1,000 inclusive, THE Money value object SHALL distribute the remainder centavos one each to the first R parts (where R = total mod N) and return an array of N integers whose sum equals the original total exactly.
6. WHEN a peso value with more than 2 decimal places is provided, THE Money value object SHALL reject the value with an InvalidArgumentException.
7. WHEN a resulting centavo value is negative or exceeds 99,999,999,999, THE Money value object SHALL reject the value with an InvalidArgumentException.
8. IF an allocate operation is invoked with N less than 1 or greater than 1,000, THEN THE Money value object SHALL reject the value with an InvalidArgumentException.
9. WHEN converting centavos to pesos for display, THE Money value object SHALL format the result as "₱X,XXX.XX" using thousands separators and exactly 2 decimal places.
10. THE Money value object SHALL satisfy the round-trip property: for all valid centavo values within range, converting from centavos to pesos and back to centavos produces the original value.

### Requirement 10: Soft Deletes with Foreign Key Nullification

**User Story:** As a developer, I want financial records to be soft-deleted with automatic nullification of dependent foreign keys, so that historical data is preserved while maintaining referential consistency.

#### Acceptance Criteria

1. THE SoftDeletesWithNullify_Trait SHALL extend Laravel's built-in SoftDeletes behavior by using the SoftDeletes trait internally and adding foreign key nullification logic on top of it.
2. WHEN a model using SoftDeletesWithNullify_Trait is soft-deleted, THE trait SHALL set the deleted_at column to the current timestamp without modifying any other field values on the deleted record.
3. WHEN a query is executed on a model using SoftDeletesWithNullify_Trait, THE query SHALL exclude records where deleted_at is not null from standard results.
4. WHEN a parent record is soft-deleted, THE SoftDeletesWithNullify_Trait SHALL set all foreign key columns declared in the model's nullifiable relationships array to NULL on dependent records, regardless of whether those dependent records are themselves soft-deleted.
5. WHEN a soft-deleted record is restored, THE SoftDeletesWithNullify_Trait SHALL clear the deleted_at timestamp making the record visible in standard queries again, without re-linking previously nullified foreign keys on dependent records.
6. WHEN a soft-deleted record exists with a unique field value, THE database SHALL allow creation of a new record with the same unique field value by excluding soft-deleted rows from unique constraint enforcement.
7. IF a user attempts to soft-delete a record that has active (non-deleted) dependent transactions, THEN THE service layer SHALL reject the operation with a validation error indicating which dependent records block deletion.
8. IF a soft-deleted record is restored and a conflicting unique constraint value already exists on an active record, THEN THE system SHALL reject the restoration with an error indicating the conflicting field and existing record.

### Requirement 11: Immutable Audit Trail with Transactional Atomicity

**User Story:** As a developer, I want every financial mutation automatically logged in an immutable audit trail within the same database transaction, so that audit records are guaranteed to exist for every persisted change and cannot be tampered with.

#### Acceptance Criteria

1. WHEN a financial record is created, THE AuditService SHALL write an audit log entry with event "created" and the new field values as a JSON object within the same database transaction.
2. WHEN a financial record is updated, THE AuditService SHALL write an audit log entry with event "updated", the previous field values as a JSON object, and the new field values as a JSON object within the same database transaction.
3. WHEN a financial record is soft-deleted, THE AuditService SHALL write an audit log entry with event "deleted" and the record's field values as a JSON object within the same database transaction.
4. THE AuditLog record SHALL capture user_id, auditable_type, auditable_id, event type, old_values (JSON), new_values (JSON), and created_at timestamp.
5. IF an attempt is made to update or delete an existing AuditLog record at the application level, THEN THE AuditLog model SHALL throw an exception preventing the operation and leave the existing record unchanged.
6. WHEN the audit history for an entity is retrieved, THE AuditService SHALL return all audit log entries for that entity ordered from newest to oldest, limited to a maximum of 100 entries per request.
7. IF the AuditService fails to persist an audit log entry, THEN THE system SHALL roll back the entire database transaction including the originating financial mutation.
8. THE AuditLog created_at timestamp SHALL be stored in UTC with at least second-level precision.
9. THE HasAuditTrail trait SHALL allow each model to define which fields are auditable via an explicit array property, and only changes to those fields SHALL be recorded.
10. IF a model uses the HasAuditTrail trait but defines no auditable fields, THEN THE AuditService SHALL audit all model attributes by default excluding timestamps and soft-delete columns.
11. WHEN a financial record is updated but none of the auditable fields have changed values, THE AuditService SHALL NOT write an audit log entry for that update.

### Requirement 12: Audit Log Data Model

**User Story:** As a developer, I want a well-structured audit_logs table with appropriate indexes, so that audit history lookups are performant and data integrity is maintained.

#### Acceptance Criteria

1. THE audit_logs table SHALL have columns: id (unsigned bigint, auto-increment), user_id (unsigned bigint, nullable for system-generated events), auditable_type (string, max 255 characters), auditable_id (unsigned bigint), event, old_values, new_values, and created_at.
2. THE event column SHALL accept only the values "created", "updated", and "deleted" enforced via an ENUM constraint.
3. THE old_values column SHALL be nullable JSON (null for "created" events).
4. THE new_values column SHALL be nullable JSON (null for "deleted" events).
5. THE audit_logs table SHALL have a foreign key constraint on user_id referencing the users table with RESTRICT on delete to prevent user deletion when audit records exist.
6. THE audit_logs table SHALL have a composite index on (auditable_type, auditable_id) for entity history lookups.
7. THE audit_logs table SHALL have a composite index on (user_id, created_at) for user activity queries.

### Requirement 13: User-Scoped Data Isolation

**User Story:** As a developer, I want all queries on financial models automatically filtered to the authenticated user's records, so that data isolation between users is structurally guaranteed without relying on per-query manual filtering.

#### Acceptance Criteria

1. WHEN any query is executed on a model using BelongsToUser_Trait, THE UserScope SHALL add a WHERE user_id = {authenticated_user_id} constraint automatically.
2. WHEN a user queries for a record that belongs to a different user, THE system SHALL return a null result for single-record lookups and exclude the record from list results, producing a response indistinguishable from querying a non-existent record.
3. WHEN a new financial record is created, THE BelongsToUser_Trait SHALL assign user_id from Auth::id() on the server side, ignoring any user_id value provided in request input.
4. IF Auth::id() returns null when UserScope is applied, THEN THE system SHALL throw an AuthenticationException and halt query execution.
5. THE UserScope global scope SHALL be registered in the model boot lifecycle and SHALL NOT be removable via API parameters, GraphQL arguments, or request input.
6. THE UserScope WHERE constraint SHALL apply to SELECT, UPDATE, and DELETE queries on scoped models, including queries that include soft-deleted records via restore or trashed-inclusive retrieval methods.
7. WHEN a scoped model eager-loads or joins a relationship to another model using BelongsToUser_Trait, THE UserScope SHALL apply independently on each related model's query, preventing cross-user record access through relationship traversal.

### Requirement 14: HasMonetaryFields Trait

**User Story:** As a developer, I want Eloquent models to automatically cast monetary database columns to and from the Money value object, so that monetary values are always handled with type safety.

#### Acceptance Criteria

1. WHEN a monetary field containing a non-null integer is read from the database, THE HasMonetaryFields_Trait SHALL return a Money value object representing that centavo amount.
2. WHEN a monetary field containing a NULL value is read from the database, THE HasMonetaryFields_Trait SHALL return null.
3. WHEN a Money value object is assigned to a monetary field, THE HasMonetaryFields_Trait SHALL persist the Money object's centavo integer value in the database.
4. WHEN an integer within the range 0 to 99,999,999,999 is assigned to a monetary field, THE HasMonetaryFields_Trait SHALL persist it directly as the centavo value in the database.
5. IF an integer assigned to a monetary field is negative or exceeds 99,999,999,999, THEN THE HasMonetaryFields_Trait SHALL throw an InvalidArgumentException indicating the value is outside the valid centavo range.
6. IF a value that is neither a Money instance, an integer, nor null is assigned to a monetary field, THEN THE HasMonetaryFields_Trait SHALL throw an InvalidArgumentException indicating an unsupported type.
7. THE HasMonetaryFields_Trait SHALL require each model to declare which columns are monetary via a monetaryFields() method that returns an array of column name strings.
