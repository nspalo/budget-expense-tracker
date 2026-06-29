---
inclusion: always
---

# Finance Conventions

## Architectural Rules

1. **Financial Precision:** All monetary amounts MUST be processed and stored as integers representing the lowest currency unit (centavos) to prevent float inaccuracies. Display formatting is a presentation concern only.

2. **Currency:** Single currency — Philippine Peso (PHP/₱). Store amounts in centavos (1 peso = 100 centavos). Design the schema to allow multi-currency later (currency column on accounts) but do not implement conversion logic now.

3. **Date Boundaries:** The backend must rely entirely on continuous date ranges (`start_date` to `end_date`) for all transaction queries. Do not use hardcoded monthly text strings or custom interval columns for querying. A `billing_period` reference (year/month) on transactions is acceptable for the accrual view grouping.

4. **Database Constraints:**
   - Use standard foreign keys linking `transactions` to `categories` and `accounts`.
   - Add composite indexes on `[user_id, payment_date]` and `[user_id, billing_period]` to optimize dashboard filtering on both dimensions.

5. **Data Isolation:** Every query must pass through a user-scoped context to ensure data isolation between authenticated sessions. Use Laravel Eloquent global scopes or direct relationship chaining.

6. **Installments:** A recurring payment split (e.g., 12-month installment) is stored as a single `installment_plan` record with a schedule. Individual payment entries are generated as separate transactions linked to that plan. Do not store 12 transactions upfront — generate them as they come due.

7. **Soft Deletes:** All financial records (transactions, accounts, categories) MUST use soft deletes. Financial data is never permanently removed from the database.

8. **Audit Trail:** All mutations to financial records must be logged with who changed what and when. Use Laravel model events or a dedicated audit package. Store previous values on update/delete.
