# Requirements Document

## Introduction

Core Entities implements the three foundational domain models that all downstream features (transactions, budgets, installments) depend on: Accounts, Category Groups, and Categories. Each entity composes the foundation infrastructure traits (BelongsToUser, HasAuditTrail, SoftDeletesWithNullify, HasMonetaryFields) and exposes a service layer for CRUD operations with validation, audit logging, and soft-delete lifecycle management. This spec covers the Eloquent models, database migrations, enum types, service classes, and factory/seeder support for these three entities.

## Glossary

- **Account**: An Eloquent model representing a financial container (bank account, e-wallet, credit card, or cash) owned by a user, with a name, type, and balance stored in centavos.
- **Account_Service**: The service class responsible for Account CRUD operations including validation, audit trail delegation, and soft-delete lifecycle enforcement.
- **AccountType**: A PHP enum defining the valid account types: bank_account, e_wallet, credit_card, and cash.
- **Category_Group**: An Eloquent model representing a high-level classification for organizing categories (e.g., Needs, Wants, Savings, Others), owned by a user.
- **CategoryGroup_Service**: The service class responsible for Category Group CRUD operations including validation, audit trail delegation, and soft-delete lifecycle enforcement.
- **Category**: An Eloquent model representing a user-defined classification for transactions (e.g., Electricity, Food, Transport), belonging to a category group and owned by a user.
- **Category_Service**: The service class responsible for Category CRUD operations including validation, audit trail delegation, and soft-delete lifecycle enforcement.
- **CategoryType**: A PHP enum defining the valid category types: expense and income.
- **Soft_Delete**: A deletion strategy where records are marked with a deleted_at timestamp but remain in the database for audit and recovery purposes.
- **Audit_Trail**: A log entry capturing who changed what and when, written within the same database transaction as the originating mutation via the AuditService.
- **Default_Category_Groups**: The four category groups (Needs, Wants, Savings, Others) automatically created when a new user registers.
- **UserScope**: The Eloquent global scope that filters all queries to the authenticated user's records, applied via the BelongsToUser trait.
- **Money**: The value object that encapsulates monetary amounts as integer centavos, used for the Account balance field.

## Requirements

### Requirement 1: Account Model and Migration

**User Story:** As a developer, I want an Account Eloquent model with a properly indexed migration, so that account data is persisted with type safety, monetary precision, and user isolation.

#### Acceptance Criteria

1. THE Account model SHALL compose the BelongsToUser_Trait, HasAuditTrail_Trait, SoftDeletesWithNullify_Trait, and HasMonetaryFields_Trait.
2. THE accounts migration SHALL define columns: id (unsigned bigint, auto-increment), user_id (unsigned bigint, foreign key referencing users.id with CASCADE on delete), name (string, max 100 characters), type (string, max 20 characters), balance_centavos (unsigned bigint, default 0), created_at (timestamp), updated_at (timestamp), and deleted_at (nullable timestamp).
3. THE accounts migration SHALL include a composite unique index on (user_id, name) scoped to records where deleted_at is null to enforce name uniqueness among active accounts per user.
4. THE accounts migration SHALL include an index on (user_id, type) to support filtered account listings.
5. THE Account model SHALL declare balance_centavos as a monetary field via the monetaryFields() method, enabling automatic Money value object casting.
6. THE Account model SHALL declare name, type, and balance_centavos as auditable fields via the getAuditableFields() method.
7. THE Account model SHALL cast the type attribute to the AccountType enum.
8. THE Account model SHALL declare name, type, and balance_centavos as mass-assignable fields, with user_id assigned by the BelongsToUser_Trait.

### Requirement 2: Account Service — Create

**User Story:** As a user, I want to create financial accounts with a name, type, and optional initial balance, so that I can track which account each transaction belongs to.

#### Acceptance Criteria

1. WHEN a user creates an account with a name (1 to 100 characters after trimming leading and trailing whitespace), a valid AccountType, and an optional initial balance provided in centavos (integer, range 0 to 99,999,999,999), THE Account_Service SHALL persist the account with the balance stored in centavos, defaulting to 0 centavos when no initial balance is provided.
2. THE Account_Service SHALL support account types of bank_account, e_wallet, credit_card, and cash via the AccountType enum.
3. IF a user attempts to create an account with a type not represented in the AccountType enum, THEN THE Account_Service SHALL reject the request and return a validation error indicating the type is invalid.
4. IF a user attempts to create an account with a name that is empty, contains only whitespace, or exceeds 100 characters after trimming leading and trailing whitespace, THEN THE Account_Service SHALL reject the request and return a validation error indicating the name length constraint.
5. IF a user attempts to create an account with a name that duplicates the name of another non-deleted account belonging to the same user (compared after trimming), THEN THE Account_Service SHALL reject the request and return a validation error indicating the name is already in use.
6. WHEN an account is successfully created, THE Account_Service SHALL record an Audit_Trail entry with event "created" and the new field values within the same database transaction.
7. IF a user attempts to create an account with an initial balance outside the valid range (less than 0 or greater than 99,999,999,999 centavos), THEN THE Account_Service SHALL reject the request and return a validation error indicating the balance is out of range.

### Requirement 3: Account Service — Update

**User Story:** As a user, I want to update my account's name or type, so that I can correct mistakes or reflect changes in my financial setup.

#### Acceptance Criteria

1. WHEN a user updates an account's name or type, THE Account_Service SHALL persist the changes and record an Audit_Trail entry with event "updated" and the previous values for each changed field within the same database transaction.
2. IF a user attempts to update an account with a name that is empty or exceeds 100 characters, THEN THE Account_Service SHALL reject the request and return a validation error indicating the name length constraint.
3. IF a user attempts to update an account with a name that duplicates another non-deleted account belonging to the same user, THEN THE Account_Service SHALL reject the request and return a validation error indicating the name is already in use.
4. IF a user attempts to update an account with a type not represented in the AccountType enum, THEN THE Account_Service SHALL reject the request and return a validation error indicating the type is invalid.
5. WHEN a user updates an account but none of the auditable fields have changed values, THE Account_Service SHALL complete the request without recording an Audit_Trail entry and without modifying the updated_at timestamp.
6. IF a user attempts to update an account that does not exist, does not belong to the authenticated user, or is soft-deleted, THEN THE Account_Service SHALL return a not-found response.
7. IF a user attempts to modify the balance_centavos field through the update operation, THEN THE Account_Service SHALL reject the request and return a validation error indicating that balance is not directly updatable.

### Requirement 4: Account Service — Delete

**User Story:** As a user, I want to delete an account I no longer use, so that it does not clutter my active account list.

#### Acceptance Criteria

1. WHEN a user deletes an account, THE Account_Service SHALL perform a Soft_Delete on the account record and record an Audit_Trail entry with event "deleted" and the account's field values at the time of deletion within the same database transaction.
2. IF a user attempts to delete an account that has non-deleted transactions linked to it, THEN THE Account_Service SHALL reject the deletion and return an error indicating the account still has active transactions.
3. WHEN an account is soft-deleted, THE Account_Service SHALL set the deleted_at timestamp without modifying any other field values on the account record.
4. IF a user attempts to delete an account that does not exist, does not belong to the authenticated user, or is already soft-deleted, THEN THE Account_Service SHALL return a not-found response.

### Requirement 5: Account Service — List and Retrieve

**User Story:** As a user, I want to view my accounts, so that I can see my financial containers and their balances.

#### Acceptance Criteria

1. THE Account_Service SHALL return only non-deleted accounts belonging to the authenticated user, ordered by name ascending.
2. WHEN a user requests a specific account by ID, THE Account_Service SHALL return the account only if it belongs to the authenticated user and is not soft-deleted, returning a not-found response otherwise.
3. IF no non-deleted accounts exist for the authenticated user, THEN THE Account_Service SHALL return an empty collection.

### Requirement 6: Category Group Model and Migration

**User Story:** As a developer, I want a CategoryGroup Eloquent model with a properly indexed migration, so that category group data is persisted with user isolation and name uniqueness enforcement.

#### Acceptance Criteria

1. THE CategoryGroup model SHALL compose the BelongsToUser_Trait, HasAuditTrail_Trait, and SoftDeletesWithNullify_Trait.
2. THE category_groups migration SHALL define columns: id (unsigned bigint, auto-increment), user_id (unsigned bigint, foreign key referencing users.id with CASCADE on delete), name (string, max 50 characters), sort_order (unsigned integer, default 0), created_at (timestamp), updated_at (timestamp), and deleted_at (nullable timestamp).
3. THE category_groups migration SHALL include a composite unique index on (user_id, name) scoped to records where deleted_at is null to enforce name uniqueness among active groups per user.
4. THE CategoryGroup model SHALL declare name and sort_order as auditable fields via the getAuditableFields() method.
5. THE CategoryGroup model SHALL define a hasMany relationship to Category, enabling the CategoryGroup_Service to check for non-deleted associated categories before permitting deletion.
6. THE CategoryGroup model SHALL declare name and sort_order as mass-assignable fields, with user_id assigned by the BelongsToUser_Trait.

### Requirement 7: Category Group Service — Create

**User Story:** As a user, I want to create category groups to organize my spending categories, so that I can set group-level budgets and view spending summaries at a higher level.

#### Acceptance Criteria

1. WHEN a user creates a category group with a name (1 to 50 characters) and an optional sort_order, THE CategoryGroup_Service SHALL persist the group linked to the authenticated user with the sort_order defaulting to 0 when not provided, and record an Audit_Trail entry with event "created" and the new values of name and sort_order within the same database transaction.
2. IF a user attempts to create a category group with a name that is empty, consists only of whitespace characters, or exceeds 50 characters after trimming leading and trailing whitespace, THEN THE CategoryGroup_Service SHALL reject the request and return a validation error indicating the name length constraint.
3. IF a user attempts to create a category group with a name that duplicates another non-deleted group belonging to the same user using case-insensitive comparison after trimming, THEN THE CategoryGroup_Service SHALL reject the operation and return an error indicating the name is already in use.

### Requirement 8: Category Group Service — Update

**User Story:** As a user, I want to rename my category groups, so that I can adjust my organizational structure over time.

#### Acceptance Criteria

1. WHEN a user updates a category group's name or sort_order, THE CategoryGroup_Service SHALL persist the changes and record an Audit_Trail entry with the previous value for each changed field within the same database transaction.
2. IF a user attempts to update a category group with a name that is empty, contains only whitespace, or exceeds 50 characters, THEN THE CategoryGroup_Service SHALL reject the request and return a validation error indicating the name length constraint.
3. IF a user attempts to update a category group with a name that duplicates another non-deleted group belonging to the same user using case-insensitive comparison, THEN THE CategoryGroup_Service SHALL reject the operation and return an error indicating the name is already in use.
4. WHEN a user updates a category group but none of the auditable fields (name, sort_order) have changed values, THE CategoryGroup_Service SHALL persist the request without recording an Audit_Trail entry.
5. IF a user attempts to update a category group that does not exist, is soft-deleted, or does not belong to the authenticated user, THEN THE CategoryGroup_Service SHALL return a not-found response.

### Requirement 9: Category Group Service — Delete

**User Story:** As a user, I want to delete a category group I no longer need, so that it does not clutter my group list.

#### Acceptance Criteria

1. WHEN a user deletes a category group, THE CategoryGroup_Service SHALL perform a Soft_Delete on the group record and record an Audit_Trail entry with event "deleted" and the group's field values at the time of deletion within the same database transaction.
2. IF a user attempts to delete a category group that has non-deleted categories linked to it, THEN THE CategoryGroup_Service SHALL reject the deletion and return an error indicating the group still has active categories.
3. WHEN a category group is soft-deleted, THE CategoryGroup_Service SHALL set the deleted_at timestamp without modifying any other field values on the group record.
4. IF a user attempts to delete a category group that does not exist, does not belong to the authenticated user, or is already soft-deleted, THEN THE CategoryGroup_Service SHALL return a not-found response.

### Requirement 10: Category Group Service — List and Retrieve

**User Story:** As a user, I want to view my category groups, so that I can see how my categories are organized.

#### Acceptance Criteria

1. THE CategoryGroup_Service SHALL return only non-deleted category groups belonging to the authenticated user, ordered by sort_order ascending.
2. WHEN a user requests a specific category group by ID, THE CategoryGroup_Service SHALL return the group only if it belongs to the authenticated user and is not soft-deleted; requests for non-existent IDs, soft-deleted records, and records belonging to other users SHALL all receive the same not-found response.

### Requirement 11: Default Category Groups on Registration

**User Story:** As a new user, I want default category groups pre-created when I register, so that I can start organizing categories immediately without manual setup.

#### Acceptance Criteria

1. WHEN a new user completes registration, THE System SHALL create four default category groups for that user within the same database transaction as user record creation: Needs (sort_order 1), Wants (sort_order 2), Savings (sort_order 3), and Others (sort_order 4), each with an Audit_Trail entry recording event "created".
2. THE default category groups SHALL be indistinguishable from user-created groups, meaning the user may rename, reorder, or delete any default group subject only to the standard CategoryGroup_Service validation rules defined in Requirements 7 through 9.
3. IF the creation of default category groups fails during registration, THEN THE System SHALL roll back the entire registration transaction including the user record and token creation, and return a registration failure response to the caller indicating that the account could not be created.
4. IF a user already has category groups at the time of registration completion (duplicate event or race condition), THEN THE System SHALL skip creation of default category groups rather than producing duplicate entries.

### Requirement 12: Category Model and Migration

**User Story:** As a developer, I want a Category Eloquent model with a properly indexed migration, so that category data is persisted with type safety, user isolation, and group association.

#### Acceptance Criteria

1. THE Category model SHALL compose the BelongsToUser_Trait, HasAuditTrail_Trait, and SoftDeletesWithNullify_Trait.
2. THE categories migration SHALL define columns: id (unsigned bigint, auto-increment), user_id (unsigned bigint, foreign key referencing users.id with CASCADE on delete), category_group_id (unsigned bigint, nullable, foreign key referencing category_groups.id with SET NULL on delete), name (string, max 50 characters), type (string, max 10 characters), icon (string, max 50 characters, nullable), color (string, max 7 characters, nullable), sort_order (unsigned integer, default 0), created_at (timestamp), updated_at (timestamp), and deleted_at (nullable timestamp).
3. THE categories migration SHALL include a composite unique index on (user_id, name, type) scoped to records where deleted_at is null to enforce name uniqueness within the same category type per user.
4. THE categories migration SHALL include an index on (user_id, category_group_id) to support filtered category listings by group.
5. THE Category model SHALL declare name, type, icon, color, sort_order, and category_group_id as auditable fields via the getAuditableFields() method.
6. THE Category model SHALL cast the type attribute to the CategoryType enum.
7. THE Category model SHALL define a belongsTo relationship to CategoryGroup.
8. THE Category model SHALL declare name, type, icon, color, sort_order, and category_group_id as fillable attributes to support mass assignment during creation and update operations.

### Requirement 13: Category Service — Create

**User Story:** As a user, I want to create expense and income categories within groups, so that I can classify my transactions for budgeting purposes.

#### Acceptance Criteria

1. WHEN a user creates a category with a name (1 to 50 characters), a valid CategoryType, and a required category group reference, THE Category_Service SHALL persist the category linked to the authenticated user and the specified group, and record an Audit_Trail entry with event "created" within the same database transaction.
2. THE Category_Service SHALL support category types of expense and income via the CategoryType enum.
3. IF a user attempts to create a category with a type not represented in the CategoryType enum, THEN THE Category_Service SHALL reject the request and return a validation error indicating the type is invalid.
4. IF a user attempts to create a category with a name that is empty or exceeds 50 characters, THEN THE Category_Service SHALL reject the request and return a validation error indicating the name length constraint.
5. IF a user attempts to create a category with a name that duplicates another non-deleted category of the same type belonging to the same user, THEN THE Category_Service SHALL reject the operation and return an error indicating the name is already in use for that type.
6. IF a user attempts to create a category referencing a category group that does not exist, does not belong to the authenticated user, or is soft-deleted, THEN THE Category_Service SHALL reject the request and return a validation error indicating the group reference is invalid.
7. WHEN a user creates a category with optional fields (icon up to 50 characters, color up to 7 characters, sort_order as unsigned integer defaulting to 0), THE Category_Service SHALL persist the provided values or apply defaults for omitted fields.

### Requirement 14: Category Service — Update

**User Story:** As a user, I want to update my categories' details, so that I can reorganize, rename, or restyle them as my needs change.

#### Acceptance Criteria

1. WHEN a user updates a category's mutable fields (name, icon, color, sort_order, or category_group_id), THE Category_Service SHALL persist the changes and record an Audit_Trail entry with the previous value for each changed field within the same database transaction.
2. IF a user attempts to update a category with a name that is empty or exceeds 50 characters, THEN THE Category_Service SHALL reject the request and return a validation error indicating the name length constraint.
3. IF a user attempts to update a category resulting in a duplicate name within the same category type for that user, THEN THE Category_Service SHALL reject the operation and return an error indicating the name is already in use for that type.
4. IF a user attempts to assign a category_group_id that does not belong to the authenticated user or is soft-deleted, THEN THE Category_Service SHALL reject the request and return a validation error indicating the group is invalid.
5. WHEN a user updates a category but none of the auditable fields have changed values, THE Category_Service SHALL persist the request without recording an Audit_Trail entry.
6. IF a user attempts to modify the type field of an existing category, THEN THE Category_Service SHALL reject the request and return a validation error indicating the type cannot be changed after creation.
7. IF a user attempts to update a category that does not exist or is soft-deleted, THEN THE Category_Service SHALL return a not-found response.
8. IF a user attempts to update a category with an icon value exceeding 50 characters or a color value exceeding 7 characters, THEN THE Category_Service SHALL reject the request and return a validation error indicating the field length constraint.

### Requirement 15: Category Service — Delete

**User Story:** As a user, I want to delete a category I no longer use, so that it does not clutter my category list.

#### Acceptance Criteria

1. WHEN a user deletes a category, THE Category_Service SHALL perform a Soft_Delete on the category record and record an Audit_Trail entry with event "deleted" and the category's field values at the time of deletion within the same database transaction.
2. IF a user attempts to delete a category that has non-deleted transactions linked to it, THEN THE Category_Service SHALL reject the deletion and return an error indicating the category has active transactions.
3. WHEN a category is soft-deleted, THE Category_Service SHALL set the deleted_at timestamp without modifying any other field values on the category record.
4. IF a user attempts to delete a category that does not exist, does not belong to the authenticated user, or is already soft-deleted, THEN THE Category_Service SHALL return a not-found response.

### Requirement 16: Category Service — List and Retrieve

**User Story:** As a user, I want to view my categories, so that I can see what classifications are available for my transactions.

#### Acceptance Criteria

1. THE Category_Service SHALL return only non-deleted categories belonging to the authenticated user, ordered by sort_order ascending then by name ascending.
2. WHEN a user requests categories filtered by category group, THE Category_Service SHALL return only non-deleted categories belonging to the specified group and the authenticated user.
3. WHEN a user requests categories filtered by category type, THE Category_Service SHALL return only non-deleted categories of the specified type belonging to the authenticated user.
4. WHEN a user requests categories filtered by both category group and category type, THE Category_Service SHALL return only non-deleted categories matching both the specified group and type belonging to the authenticated user.
5. IF a user requests categories filtered by a category group that does not exist or does not belong to the authenticated user, THEN THE Category_Service SHALL return an empty result set.
6. IF a user requests categories filtered by a type value not represented in the CategoryType enum, THEN THE Category_Service SHALL reject the request and return a validation error indicating the type is invalid.
7. WHEN a user requests a specific category by ID, THE Category_Service SHALL return the category only if it belongs to the authenticated user and is not soft-deleted, returning a not-found response otherwise.

### Requirement 17: AccountType Enum

**User Story:** As a developer, I want a typed enum for account types, so that invalid values are caught at compile time and the set of valid types is maintained in a single location.

#### Acceptance Criteria

1. THE AccountType enum SHALL define exactly four cases: BankAccount (value "bank_account"), EWallet (value "e_wallet"), CreditCard (value "credit_card"), and Cash (value "cash").
2. THE AccountType enum SHALL be a PHP backed string enum (`: string`) that implements the native enum interface required for Eloquent attribute casting, such that declaring `'type' => AccountType::class` in a model's `$casts` array correctly serializes to and deserializes from the backing string value.
3. WHEN `AccountType::tryFrom()` is called with a string not matching any of the four defined backing values ("bank_account", "e_wallet", "credit_card", "cash"), THE AccountType enum SHALL return null, enabling calling code to detect invalid account types without throwing an exception.

### Requirement 18: CategoryType Enum

**User Story:** As a developer, I want a typed enum for category types, so that invalid values are caught at compile time and the set of valid types is maintained in a single location.

#### Acceptance Criteria

1. THE CategoryType enum SHALL define exactly two cases: Expense (value "expense") and Income (value "income").
2. THE CategoryType enum SHALL be a native PHP 8.1+ backed string enum (`: string`) usable as an Eloquent attribute cast without requiring additional interfaces or traits.
3. THE CategoryType enum file SHALL declare `strict_types=1` and reside in the `App\Enums` namespace.

### Requirement 19: Model Factories and Seeders

**User Story:** As a developer, I want factories and seeders for Account, CategoryGroup, and Category, so that I can generate test data consistently and seed default data for development.

#### Acceptance Criteria

1. THE AccountFactory SHALL generate valid Account instances with a random name (1 to 100 characters), a random AccountType selected from the AccountType enum, and a random balance_centavos integer within the range 0 to 99,999,999,999.
2. THE CategoryGroupFactory SHALL generate valid CategoryGroup instances with a random name (1 to 50 characters) and a random sort_order integer within the range 0 to 99.
3. THE CategoryFactory SHALL generate valid Category instances with a random name (1 to 50 characters), a random CategoryType selected from the CategoryType enum, a valid category_group_id referencing an existing or factory-created CategoryGroup belonging to the same user, a random sort_order integer within the range 0 to 99, icon defaulting to null, and color defaulting to null.
4. THE DatabaseSeeder SHALL create exactly one demo user with four accounts (one per AccountType: BankAccount, EWallet, CreditCard, Cash), the four default category groups (Needs, Wants, Savings, Others with sort_order 1 through 4), and at least two categories per category group with both expense and income types represented across the full set.
5. WHEN a factory is invoked without an explicit user association, THE factory SHALL create and associate a new User instance via the factory's default definition to satisfy the user_id foreign key constraint.
6. WHEN the DatabaseSeeder is executed on a database that already contains the demo user, THE DatabaseSeeder SHALL skip creation and leave existing demo data unchanged to allow repeated execution without duplication.
