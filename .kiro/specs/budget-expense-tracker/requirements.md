# Requirements Document

## Implementation Plan

> Tracks how this backlog is split into focused specs for implementation. Each spec = one branch = one PR.

| # | Spec | Requirements | Branch | Status |
|---|------|---|---|---|
| 1 | foundation-infrastructure | 9, 10, 11, 13 | feature/BETA-004 | ✅ Done |
| 2 | auth-module | 1, 14 | feature/BETA-005 | ✅ Done |
| 3 | core-entities | 2, 3, 4 | feature/BETA-006 | ✅ Done |
| 4 | transactions | 5 | feature/BETA-007 | 🔄 Next |
| 5 | budgets-dashboard | 6, 7 | feature/BETA-008 | ⬜ Planned |
| 6 | installments | 8 | feature/BETA-009 | ⬜ Planned |
| 7 | graphql-api | 12 | feature/BETA-010 | ⬜ Planned |

**Dependency order:** 1 → 2 → 3 → 4 → 5 → 6 → 7 (each depends on the one before it)

## Introduction

Budget & Expense Tracker is a personal finance application built around a single continuous ledger with dual-axis tracking. Every transaction is recorded with both a billing period (accrual view — when a cost is *for*) and a payment date (cash view — when money actually leaves the account). This dual-axis approach enables users to measure their true cost of living per month while simultaneously monitoring real-time liquidity across their accounts.

The system supports account management (bank accounts, wallets, credit cards), user-defined categories organized into groups, monthly budget targets at both group and category levels, and installment plan tracking. All monetary values are stored as integers in centavos (Philippine Peso) to ensure financial precision.

## Glossary

- **Ledger:** The single continuous record of all financial transactions belonging to a user, queryable by both billing period and payment date dimensions.
- **Transaction:** A single financial entry recording money movement, containing an amount in centavos, a payment date, a billing period reference, a due date, a payment status, and links to an account and category.
- **Billing_Period:** A year-month reference (e.g., 2026-06) indicating which month a cost is attributed to for accrual reporting purposes.
- **Payment_Date:** The calendar date when money actually leaves or enters an account. Null if the transaction is pending (not yet paid).
- **Due_Date:** The date by which a bill or payment is expected to be settled. Used for tracking upcoming obligations.
- **Transaction_Status:** The payment state of a transaction: paid (money has moved), pending (logged but not yet paid), or overdue (past due_date and still unpaid).
- **Account:** A financial container representing a bank account, digital wallet, or credit card owned by the user.
- **Category_Group:** A high-level classification for organizing categories (e.g., Needs, Wants, Savings). Used for group-level budgeting and reporting.
- **Category:** A user-defined classification for transactions (e.g., Electricity, Food, Transport) that belongs to a category group.
- **Budget_Target:** A monthly spending allocation set by the user, assignable at either category level or category group level.
- **Projected:** The sum of all transactions (paid + pending) for a given period — represents total financial commitment.
- **Actual:** The sum of only paid transactions for a given period — represents real money that has moved.
- **Remaining:** The difference between allocated budget and projected spending — represents available headroom.
- **Installment_Plan:** A record representing a recurring payment split over multiple periods (e.g., a 12-month appliance payment), from which individual payment transactions are generated as they come due.
- **Centavos:** The smallest unit of Philippine Peso (1 ₱ = 100 centavos). All monetary amounts are stored and processed as integers in this unit.
- **Accrual_View:** A report perspective grouping transactions by billing period to show the true cost of living per month.
- **Cash_View:** A report perspective filtering transactions by payment date to show real-time liquidity and actual cash flow.
- **Dashboard:** The primary user interface displaying financial summaries filterable by month or bi-weekly pay cycles.
- **Soft_Delete:** A deletion strategy where records are marked as deleted (via a timestamp) but remain in the database for audit and recovery purposes.
- **Audit_Trail:** A log of all changes (create, update, delete) to financial records, capturing who changed what, when, and the previous values.
- **Global_Scope:** A Laravel Eloquent mechanism that automatically applies a user-scoping condition to all queries, ensuring data isolation between users.
- **GraphQL_API:** The primary application programming interface using GraphQL protocol, served by Lighthouse PHP.

## Requirements

### Requirement 1: User Authentication

**User Story:** As a user, I want to register and log in to the application, so that my financial data is secure and isolated from other users.

#### Acceptance Criteria

1. WHEN a registration request is submitted with a valid email address (RFC 5322 format, maximum 255 characters) and a password (minimum 8 characters, maximum 72 characters), THE Auth_System SHALL create a new user account and return an authentication token.
2. IF a registration request is submitted with an email address that already exists in the system, THEN THE Auth_System SHALL reject the request with an error indicating the email is already taken.
3. WHEN a login request is submitted with an email and password matching an existing user account, THE Auth_System SHALL return an authentication token for the session.
4. IF a login request is submitted with a non-existent email or incorrect password, THEN THE Auth_System SHALL return a generic authentication error without revealing which field is incorrect.
5. WHEN a logout request is submitted with a valid token, THE Auth_System SHALL invalidate the current authentication token so that subsequent requests using that token are rejected.
6. IF a request is made to a protected endpoint without a valid authentication token, THEN THE Auth_System SHALL reject the request with an unauthenticated error.
7. THE Auth_System SHALL scope all subsequent data queries to the authenticated user via Global_Scope so that no user can access, modify, or view another user's financial data.

### Requirement 2: Account Management

**User Story:** As a user, I want to create and manage my financial accounts, so that I can track which account each transaction belongs to.

#### Acceptance Criteria

1. WHEN a user creates an account with a name (1 to 100 characters), a valid type, and an optional initial balance, THE Account_Service SHALL persist the account with the balance stored in centavos, defaulting to 0 centavos when no initial balance is provided.
2. THE Account_Service SHALL support account types of bank_account, wallet, and credit_card.
3. IF a user attempts to create or update an account with a type not in the supported set, THEN THE Account_Service SHALL reject the request and return an error indicating the type is invalid.
4. IF a user attempts to create or update an account with a name that is empty, exceeds 100 characters, or duplicates the name of another non-deleted account belonging to the same user, THEN THE Account_Service SHALL reject the request and return an error indicating the validation failure.
5. WHEN a user updates an account name or type, THE Account_Service SHALL persist the changes and record an Audit_Trail entry with previous values.
6. WHEN a user deletes an account, THE Account_Service SHALL perform a Soft_Delete on the account record.
7. IF a user attempts to delete an account that has non-deleted transactions linked to it, THEN THE Account_Service SHALL reject the deletion and return an error indicating the account still has active transactions.
8. THE Account_Service SHALL return only accounts belonging to the authenticated user.

### Requirement 3: Category Group Management

**User Story:** As a user, I want to organize my categories into groups (e.g., Needs, Wants, Savings), so that I can set group-level budgets and view spending summaries at a higher level.

#### Acceptance Criteria

1. WHEN a user creates a category group with a name (1 to 50 characters), THE CategoryGroup_Service SHALL persist the group linked to the authenticated user.
2. IF a user attempts to create or update a category group with a name that duplicates another non-deleted group belonging to the same user, THEN THE CategoryGroup_Service SHALL reject the operation and return an error indicating the name is already in use.
3. WHEN a user updates a category group's name, THE CategoryGroup_Service SHALL persist the change and record an Audit_Trail entry with the previous value.
4. WHEN a user deletes a category group, THE CategoryGroup_Service SHALL perform a Soft_Delete on the group record.
5. IF a user attempts to delete a category group that has non-deleted categories linked to it, THEN THE CategoryGroup_Service SHALL reject the deletion and return an error indicating the group still has active categories.
6. THE CategoryGroup_Service SHALL return only non-deleted category groups belonging to the authenticated user.
7. THE System SHALL provide default category groups (Needs, Wants, Savings, Others) when a new user registers, which the user may rename or delete.

### Requirement 4: Category Management

**User Story:** As a user, I want to create and manage expense and income categories within groups, so that I can classify my transactions for budgeting purposes.

#### Acceptance Criteria

1. WHEN a user creates a category with a name (1 to 50 characters), a type, and a category group reference, THE Category_Service SHALL persist the category linked to the authenticated user and the specified group.
2. THE Category_Service SHALL support category types of expense and income.
3. WHEN a user updates a category's mutable fields (name, icon, color, sort_order, or category group reference), THE Category_Service SHALL persist the change and record an Audit_Trail entry with the previous value for each changed field.
4. WHEN a user deletes a category, THE Category_Service SHALL perform a Soft_Delete on the category record.
5. IF a user attempts to delete a category that has non-deleted transactions linked to it, THEN THE Category_Service SHALL reject the deletion and return an error message indicating the category has active transactions.
6. THE Category_Service SHALL return only non-deleted categories belonging to the authenticated user.
7. IF a user attempts to create or update a category resulting in a duplicate name within the same category type for that user, THEN THE Category_Service SHALL reject the operation and return an error message indicating the name is already in use for that type.
8. THE Category_Service SHALL validate that the referenced category group belongs to the authenticated user and is not soft-deleted before persisting.

### Requirement 5: Transaction Management

**User Story:** As a user, I want to record, edit, and remove transactions in my ledger with payment status tracking, so that I can maintain an accurate financial history and track upcoming obligations.

#### Acceptance Criteria

1. WHEN a user creates a transaction, THE Transaction_Service SHALL require a transaction type (income, expense, or transfer), an amount in centavos, a billing_period, and an account reference. A category reference SHALL be required for income and expense types, and optional for transfer types.
2. THE Transaction_Service SHALL support a payment status field with values: paid, pending, or overdue.
3. WHEN a transaction is created with status paid, THE Transaction_Service SHALL require a payment_date. WHEN created with status pending, payment_date SHALL be optional (null until paid).
4. THE Transaction_Service SHALL accept an optional due_date field representing when payment is expected. IF due_date is provided and the current date exceeds due_date while status remains pending, THE System SHALL consider the transaction overdue.
5. WHEN a valid transaction is submitted, THE Transaction_Service SHALL persist the transaction and record an Audit_Trail entry.
6. WHEN a user updates a transaction (including changing status from pending to paid), THE Transaction_Service SHALL persist the changes and record an Audit_Trail entry with previous values. IF status changes to paid and payment_date is null, THE Transaction_Service SHALL set payment_date to the current date.
7. WHEN a user deletes a transaction, THE Transaction_Service SHALL perform a Soft_Delete and record an Audit_Trail entry.
8. THE Transaction_Service SHALL store the amount as a positive integer in centavos with a minimum value of 1 and a maximum value of 99,999,999,999 (representing ₱0.01 to ₱999,999,999.99).
9. THE Transaction_Service SHALL store the billing_period as a year-month reference (YYYY-MM format).
10. THE Transaction_Service SHALL validate that the referenced account and category belong to the authenticated user before persisting.
11. IF a transaction references a non-existent or soft-deleted account or category, THEN THE Transaction_Service SHALL reject the operation and return an error message indicating which referenced entity was not found or is inactive.
12. IF a transaction is submitted with an invalid payment_date format, an invalid billing_period format, an out-of-range amount, or an unrecognized transaction type, THEN THE Transaction_Service SHALL reject the operation and return an error message indicating which field failed validation.

### Requirement 6: Dual-Axis Dashboard Queries

**User Story:** As a user, I want to view my financial summary filtered by either billing period or payment date range, so that I can analyze both my true cost of living and my real-time cash flow.

#### Acceptance Criteria

1. WHEN a user requests the Accrual_View for a given billing_period, THE Dashboard_Service SHALL return all transactions matching that billing_period for the authenticated user, including: total income in centavos, total expenses in centavos, net cash flow in centavos, budget utilization as a percentage of the assigned budget, and the top 5 spending categories ranked by total amount descending.
2. WHEN a user requests the Cash_View for a given date range, THE Dashboard_Service SHALL return all transactions with a payment_date within that range (inclusive of start and end dates) for the authenticated user, including: total income in centavos, total expenses in centavos, net cash flow in centavos, budget utilization as a percentage of the assigned budget, and the top 5 spending categories ranked by total amount descending.
3. THE Dashboard_Service SHALL support filtering by month (calendar month boundaries defined as day 1 00:00:00 through last day 23:59:59) and by bi-weekly pay cycle (user-defined start date plus 14-day intervals).
4. IF a user requests the bi-weekly pay cycle filter and no pay cycle start date has been configured for that user, THEN THE Dashboard_Service SHALL return an error response indicating that a pay cycle start date must be configured before using bi-weekly filtering.
5. IF no transactions match the selected billing_period or date range, THEN THE Dashboard_Service SHALL return zero values for all totals, an empty list for top spending categories, and a budget utilization of 0%.
6. IF no budget has been assigned for the selected period, THEN THE Dashboard_Service SHALL omit the budget utilization percentage from the response and return the remaining summary fields.
7. WHEN a user requests either the Accrual_View or Cash_View, THE Dashboard_Service SHALL return the response within 500 milliseconds for date ranges spanning up to 31 days and up to 10,000 transactions.

### Requirement 7: Budget Targets

**User Story:** As a user, I want to set monthly budget allocations at both category and group level, so that I can compare my projected and actual spending against planned limits.

#### Acceptance Criteria

1. WHEN a user creates a budget target at category level, THE Budget_Service SHALL require a category reference, a monthly amount in centavos between 1 and 99,999,999,999 (inclusive), and a billing_period in YYYY-MM format.
2. WHEN a user creates a budget target at group level, THE Budget_Service SHALL require a category group reference, a monthly amount in centavos between 1 and 99,999,999,999 (inclusive), and a billing_period in YYYY-MM format.
3. IF a user attempts to create a budget target for a category (or group) and billing_period combination that already exists for that user, THEN THE Budget_Service SHALL reject the request with an error message indicating a duplicate budget target.
4. WHEN a user requests budget performance for a billing_period at category level, THE Budget_Service SHALL return each category's allocated amount, actual amount (sum of paid transactions for that category and billing_period), and remaining amount (allocated minus actual).
5. WHEN a user requests budget performance for a billing_period at group level, THE Budget_Service SHALL return each group's allocated amount, projected amount (sum of all transactions regardless of status for all categories in that group and billing_period), actual amount (sum of only paid transactions), and remaining amount (allocated minus projected).
6. WHEN a user requests the overall budget summary for a billing_period, THE Budget_Service SHALL return the total allocated across all groups, total projected, total actual, and total remaining (allocated minus projected).
7. WHEN a user updates a budget target amount, THE Budget_Service SHALL persist the change and record an Audit_Trail entry with the previous value.
8. WHEN a user deletes a budget target, THE Budget_Service SHALL perform a Soft_Delete on the record.
9. THE Budget_Service SHALL return only budget targets belonging to the authenticated user.
10. WHEN a user copies budget targets from a previous billing_period, THE Budget_Service SHALL create new budget targets (both category-level and group-level) for the destination billing_period using the references and amounts from the source billing_period, skipping any target that already exists in the destination period.

### Requirement 8: Installment Plan Management

**User Story:** As a user, I want to create installment plans for large purchases, so that the system generates payment transactions as each installment comes due.

#### Acceptance Criteria

1. WHEN a user creates an installment plan, THE Installment_Service SHALL require a total amount in centavos (minimum 2 centavos, maximum 99,999,999,999 centavos), a number of installments (minimum 2, maximum 60), a frequency (one of: monthly, bi-weekly), a start date no earlier than today, an account reference, and a category reference.
2. THE Installment_Service SHALL store the plan as a single record without pre-generating all future transactions.
3. WHEN an installment payment comes due (determined by start date plus frequency intervals, triggered by scheduler or manual invocation), THE Installment_Service SHALL generate a transaction linked to the plan via installment_plan_id, with the calculated installment amount in centavos, billing_period set to the year and month in which the installment is due, and status set to pending with due_date set to the installment due date.
4. THE Installment_Service SHALL calculate each installment amount by dividing the total by the number of installments, distributing any remainder centavos across the earliest payments.
5. WHEN all installments have been generated, THE Installment_Service SHALL mark the plan status as completed.
6. WHEN a user views an installment plan, THE Installment_Service SHALL display the total amount, number of paid installments, number of remaining installments, and the next due date.
7. IF a user deletes an installment plan, THEN THE Installment_Service SHALL perform a Soft_Delete on the plan record without affecting already-generated transactions.
8. IF a user cancels an active installment plan, THEN THE Installment_Service SHALL set the plan status to cancelled, stop generating future installments, and retain all previously generated transactions unchanged.

### Requirement 9: Monetary Precision and Storage

**User Story:** As a user, I want all financial calculations to be precise, so that I never encounter rounding errors in my balance or reports.

#### Acceptance Criteria

1. THE System SHALL store all monetary amounts as unsigned integers representing centavos (1 ₱ = 100 centavos) within the range of 0 to 99,999,999,999 centavos (₱0.00 to ₱999,999,999.99).
2. THE System SHALL perform all arithmetic operations (sums, differences, divisions) on integer centavo values.
3. WHEN displaying monetary values to the user, THE Presentation_Layer SHALL format centavo integers as Philippine Peso with two decimal places, a thousands separator, and the ₱ symbol prefix (e.g., 150000 centavos displays as ₱1,500.00).
4. WHEN dividing an amount into N equal parts (e.g., installment calculations), THE System SHALL use integer division for each part and distribute the remainder by adding one centavo to each of the first R entries (where R = amount mod N), so that the sum of all parts equals the original amount exactly.
5. WHEN receiving a monetary value in pesos from the API boundary, THE System SHALL convert it to centavos by multiplying by 100 and rounding to the nearest integer (half-up), rejecting any input that exceeds two decimal places with a validation error indicating the value has too many decimal places.

### Requirement 10: Soft Deletion and Data Integrity

**User Story:** As a user, I want deleted financial records to be recoverable, so that accidental deletions do not result in permanent data loss.

#### Acceptance Criteria

1. THE System SHALL implement Soft_Delete on all financial records including transactions, accounts, categories, category groups, budget targets, and installment plans.
2. WHEN a record is soft-deleted, THE System SHALL set a deleted_at timestamp on the record and retain all field values unchanged.
3. THE System SHALL exclude soft-deleted records from all queries and listings except audit trail views, which SHALL include soft-deleted records with their deleted status indicated.
4. WHEN a user queries their data, THE System SHALL return only non-deleted records unless an explicit include-deleted parameter is provided.
5. WHEN a user requests restoration of a soft-deleted record, THE System SHALL clear the deleted_at timestamp and make the record visible in standard queries again.
6. WHEN an account or category is soft-deleted, THE System SHALL set the corresponding foreign key reference on linked transactions to null (nullOnDelete) so that those transactions remain queryable.
7. IF a user creates a record with the same unique identifying values as an existing soft-deleted record, THEN THE System SHALL allow creation by scoping unique constraints to exclude soft-deleted records.

### Requirement 11: Audit Trail

**User Story:** As a user, I want all changes to my financial data to be logged, so that I can review the history of modifications for accountability.

#### Acceptance Criteria

1. WHEN a financial record is created, THE Audit_System SHALL log the creation event with the user identifier, timestamp, auditable entity type, auditable entity identifier, and the new values.
2. WHEN a financial record is updated, THE Audit_System SHALL log the update event with the user identifier, timestamp, auditable entity type, auditable entity identifier, previous values, and new values.
3. WHEN a financial record is soft-deleted, THE Audit_System SHALL log the deletion event with the user identifier, timestamp, auditable entity type, auditable entity identifier, and the previous values of the deleted record.
4. THE Audit_System SHALL capture audit events for transactions, accounts, categories, category groups, budget targets, and installment plans.
5. THE Audit_System SHALL store audit records independently from the audited records so that audit history persists regardless of the audited record state, and audit records SHALL be immutable — no update or delete operations are permitted on audit records.
6. WHEN a user requests the change history for a specific financial record, THE Audit_System SHALL return all audit entries for that record filtered by auditable entity type and auditable entity identifier, ordered by timestamp descending.
7. IF the Audit_System fails to persist an audit record, THEN THE Audit_System SHALL prevent the originating create, update, or delete operation from completing, so that no financial mutation occurs without a corresponding audit trail.
8. WHEN an audit event is logged, THE Audit_System SHALL record the timestamp in UTC with a precision of at least one second.

### Requirement 12: GraphQL API

**User Story:** As a frontend developer, I want a GraphQL API exposing all financial operations, so that the Vue.js SPA can efficiently query and mutate data.

#### Acceptance Criteria

1. THE GraphQL_API SHALL expose query operations for listing and retrieving transactions, accounts, categories, category groups, budget targets, and installment plans.
2. THE GraphQL_API SHALL expose mutation operations for creating, updating, and deleting transactions, accounts, categories, category groups, budget targets, and installment plans.
3. THE GraphQL_API SHALL require a valid authentication token on all operations.
4. IF a request is made without a valid authentication token, THEN THE GraphQL_API SHALL return an authentication error indicating the request is unauthenticated and reject the operation without executing resolvers.
5. IF a mutation request contains invalid input, THEN THE GraphQL_API SHALL return a validation error response that includes the field name and a human-readable reason for each invalid field, without persisting any changes.
6. THE GraphQL_API SHALL support offset-based pagination on all list queries with a default page size of 25 items and a maximum page size of 100 items.
7. THE GraphQL_API SHALL accept monetary input values as Float representing pesos and return monetary output values as Float representing pesos, with conversion to and from centavos occurring at the resolver boundary.
8. THE GraphQL_API SHALL support filtering list queries for transactions by billing_period (year and month), by payment_date range (start date and end date), and by status (paid, pending, overdue), independently or in combination.
9. IF a list query requests a page size exceeding 100 items, THEN THE GraphQL_API SHALL cap the returned results at 100 items.

### Requirement 13: Data Isolation

**User Story:** As a user, I want assurance that no other user can access my financial data, so that my information remains private and secure.

#### Acceptance Criteria

1. THE System SHALL apply a Global_Scope to all queries on the following financial models: transactions, accounts, categories, category_groups, budgets, and installment_plans, restricting results to records where user_id matches the authenticated user.
2. IF a user attempts to read, update, or delete a record belonging to another user via direct ID reference, THEN THE System SHALL return a not-found response identical to the response for a non-existent record.
3. WHEN a user creates a financial record, THE System SHALL assign the user_id from the server-side authenticated session, ignoring any user_id value provided in the request input.
4. THE System SHALL enforce user ownership validation on all update and delete operations for financial records by verifying the target record's user_id matches the authenticated user before performing the operation.
5. THE System SHALL NOT expose any API endpoint or request parameter that bypasses the Global_Scope; scope removal SHALL be restricted to system-level background processes that do not accept external user input.

### Requirement 14: User Profile & Settings

**User Story:** As a user, I want to manage my profile information and application preferences, so that I can keep my account up to date and customize the app to my needs.

#### Acceptance Criteria

1. WHEN a user requests their profile, THE Profile_Service SHALL return the user's name, email, and configured preferences (pay cycle start date, default account, timezone).
2. WHEN a user updates their name (1 to 100 characters), THE Profile_Service SHALL persist the change and record an Audit_Trail entry with the previous value.
3. WHEN a user updates their email address, THE Profile_Service SHALL require re-authentication (current password confirmation) and persist the change only after successful verification.
4. IF a user updates their email to an address already in use by another account, THEN THE Profile_Service SHALL reject the request with an error indicating the email is already taken.
5. WHEN a user changes their password, THE Profile_Service SHALL require the current password for verification and a new password (minimum 8 characters, maximum 72 characters), and SHALL reject the change if the current password is incorrect.
6. WHEN a user sets or updates their pay cycle start date, THE Profile_Service SHALL persist the date and use it for bi-weekly pay cycle calculations in the Dashboard_Service.
7. WHEN a user sets or updates their default account preference, THE Profile_Service SHALL validate that the referenced account belongs to the authenticated user and is not soft-deleted before persisting.
8. WHEN a user sets or updates their timezone preference, THE Profile_Service SHALL validate the timezone against the IANA timezone database and persist the value for date display formatting.
9. IF a user requests account deletion, THEN THE Profile_Service SHALL require re-authentication, perform a hard delete of all user data (accounts, categories, category groups, transactions, budgets, installment plans, audit records, and the user record itself), and invalidate all active tokens.
