---
inclusion: fileMatch
fileMatchPattern: "**/*.php"
---

# Database Standards

## Query Patterns

### Always Eager Load Relationships
- Use `with()` to prevent N+1 queries
- Define default eager loads in model's `$with` property for always-needed relationships
- Use `withCount()` for aggregate counts without loading full collections

```php
// Bad: N+1
$transactions = Transaction::all();
foreach ($transactions as $t) {
    echo $t->category->name; // Fires a query per transaction
}

// Good: Eager loaded
$transactions = Transaction::with(['category', 'account'])->get();
```

### Scope Everything to User
- All financial model queries MUST be scoped to the authenticated user
- Use a global scope trait on financial models:

```php
trait BelongsToAuthenticatedUser
{
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && !$model->user_id) {
                $model->user_id = auth()->id();
            }
        });
    }
}
```

### Use Database Transactions for Multi-Step Operations
- Wrap related writes in `DB::transaction()`
- Installment plan creation + initial transaction = one transaction
- Budget updates that affect multiple records = one transaction

```php
DB::transaction(function () use ($data) {
    $plan = InstallmentPlan::create($data);
    $this->generateNextInstallment($plan);
});
```

### Chunking for Large Datasets
- Use `chunk()` or `lazy()` for processing large record sets
- Never `Model::all()` on tables that could grow unbounded
- Reports and exports should use `cursor()` for memory efficiency

## Indexing Strategy

### Composite Index Rules
- Column order matters: most-selective column first for equality, range columns last
- The `user_id` always comes first (every query is user-scoped)
- Payment date and billing period indexes cover the dual-axis queries

### When to Add an Index
- Any column used in `WHERE` clauses regularly
- Any column used in `ORDER BY` on large tables
- Foreign keys (Laravel adds these automatically with `foreignId`)
- Don't index columns with very low cardinality (e.g., boolean `is_active` alone)

### Index Naming
- Format: `idx_{table}_{columns}` (e.g., `idx_transactions_user_payment`)
- Unique constraints: `uq_{table}_{columns}` (e.g., `uq_budget_user_cat_period`)

## Soft Deletes

- All financial models use `SoftDeletes`
- Queries automatically exclude soft-deleted records via Eloquent
- Use `withTrashed()` only for audit/admin views
- Unique constraints must account for soft deletes (use partial indexes or include `deleted_at`)

## Query Performance

### Avoid
- `SELECT *` — always specify columns in complex queries (Eloquent does this naturally for models)
- Subqueries in loops — use joins or eager loading
- Unbounded `LIKE '%search%'` queries — use indexed prefix matches or full-text search
- Calling `count()` separately when you already need the collection

### Prefer
- Aggregate queries at the database level (`SUM`, `COUNT`, `AVG`) over PHP-side calculation
- `selectRaw()` for computed columns in reports
- Query builder for complex reports, Eloquent for CRUD operations
- Pagination for all list endpoints — never return unbounded result sets

## Data Integrity

### Foreign Key Behavior
- `user_id` → `cascadeOnDelete()` (delete user = delete all their data)
- `category_id` → `nullOnDelete()` (delete category = transactions keep their data, category becomes null)
- `account_id` → `nullOnDelete()` (same rationale)
- `installment_plan_id` → `nullOnDelete()` (individual transactions survive plan deletion)

### Validation at Multiple Levels
1. **Form Request** — input format validation (required, string, date format)
2. **Service** — business rule validation (budget not exceeded, valid billing period)
3. **Database** — constraints as final safety net (foreign keys, unique indexes, NOT NULL)

### Enum Values
- Store as strings in the database (`VARCHAR`), not MySQL ENUM type
- MySQL ENUM requires `ALTER TABLE` to add values — string columns don't
- Validate against PHP Enum cases in form requests
- Cast to PHP Enum in model `$casts`

```php
// Model
protected function casts(): array
{
    return ['type' => TransactionType::class];
}

// Enum
enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
}
```

## Seeding

- Use factories for test data, seeders for reference data
- Categories should have a seeder with sensible defaults (Utilities, Food, Transport, etc.)
- Never seed user-specific financial data in production seeders
- Development seeders can generate realistic sample data for UI testing
