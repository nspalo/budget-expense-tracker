---
inclusion: fileMatch
fileMatchPattern: "**/*migration*.php"
---

# Migration Guide

## Naming Convention

Migrations follow Laravel's timestamp format with descriptive names:
```
yyyy_mm_dd_hhmmss_{action}_{table}_table.php
```

Actions: `create`, `add`, `modify`, `drop`, `rename`

Examples:
- `2026_07_01_000001_create_accounts_table.php`
- `2026_07_01_000002_create_categories_table.php`
- `2026_07_01_000003_create_transactions_table.php`
- `2026_07_01_000004_create_budgets_table.php`
- `2026_07_01_000005_create_installment_plans_table.php`
- `2026_07_01_100000_add_billing_period_index_to_transactions_table.php`

## Schema Rules for Financial Tables

### Required columns on ALL financial tables
```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->timestamps();
$table->softDeletes();
```

### Money columns
- Always use `unsignedBigInteger` for money (centavos can be large for aggregations)
- Column name suffix: `_centavos` to make the unit explicit
- Never use `decimal`, `float`, or `double` for money

```php
$table->unsignedBigInteger('amount_centavos');
$table->unsignedBigInteger('total_centavos');
$table->unsignedBigInteger('target_amount_centavos');
```

### Date columns
- `payment_date` — when money moves (DATE type)
- `billing_period` — which month the cost belongs to (CHAR(7), format 'YYYY-MM')

```php
$table->date('payment_date');
$table->char('billing_period', 7); // '2026-06'
```

### Foreign keys
- Always use `foreignId()->constrained()` for referential integrity
- Financial records use `nullOnDelete()` for category/account FKs (preserve transaction if category deleted)
- User FK uses `cascadeOnDelete()` (user deletion removes all their data)

```php
$table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('account_id')->constrained()->nullOnDelete();
$table->foreignId('installment_plan_id')->nullable()->constrained()->nullOnDelete();
```

### Enums
- Use string columns with PHP Enum casts, not MySQL ENUM type (easier to migrate)
```php
$table->string('type', 20); // 'income', 'expense', 'transfer'
$table->string('status', 20)->default('active');
```

## Required Indexes

### Composite indexes for dashboard performance
```php
// Dual-axis query optimization
$table->index(['user_id', 'payment_date'], 'idx_user_payment_date');
$table->index(['user_id', 'billing_period'], 'idx_user_billing_period');

// Category-based filtering
$table->index(['user_id', 'category_id', 'billing_period'], 'idx_user_category_period');
```

### Always name indexes explicitly
Use the pattern: `idx_{table}_{columns}` for clarity in debugging.

## Table Design Reference

### accounts
```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name', 100);
$table->string('type', 30);           // 'bank', 'wallet', 'credit_card'
$table->string('currency', 3)->default('PHP');
$table->bigInteger('balance_centavos')->default(0);
$table->boolean('is_active')->default(true);
$table->timestamps();
$table->softDeletes();
```

### categories
```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name', 100);
$table->string('type', 20);           // 'expense', 'income'
$table->string('icon', 50)->nullable();
$table->string('color', 7)->nullable(); // hex color
$table->unsignedInteger('sort_order')->default(0);
$table->boolean('is_active')->default(true);
$table->timestamps();
$table->softDeletes();
```

### transactions
```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('account_id')->constrained()->nullOnDelete();
$table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('installment_plan_id')->nullable()->constrained()->nullOnDelete();
$table->string('type', 20);            // 'income', 'expense', 'transfer'
$table->unsignedBigInteger('amount_centavos');
$table->date('payment_date');
$table->char('billing_period', 7);     // 'YYYY-MM'
$table->string('description', 255)->nullable();
$table->json('metadata')->nullable();   // flexible extra data
$table->timestamps();
$table->softDeletes();

// Indexes
$table->index(['user_id', 'payment_date'], 'idx_transactions_user_payment');
$table->index(['user_id', 'billing_period'], 'idx_transactions_user_period');
$table->index(['user_id', 'category_id', 'billing_period'], 'idx_transactions_user_cat_period');
```

### budgets
```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('category_id')->constrained()->cascadeOnDelete();
$table->unsignedBigInteger('target_amount_centavos');
$table->char('period', 7);             // 'YYYY-MM' — which month this budget is for
$table->timestamps();
$table->softDeletes();

$table->unique(['user_id', 'category_id', 'period'], 'uq_budget_user_cat_period');
```

### installment_plans
```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('account_id')->constrained()->nullOnDelete();
$table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
$table->string('name', 150);
$table->unsignedBigInteger('total_amount_centavos');
$table->unsignedInteger('total_installments');
$table->unsignedInteger('completed_installments')->default(0);
$table->unsignedBigInteger('installment_amount_centavos');
$table->string('frequency', 20);       // 'monthly', 'bi-weekly'
$table->date('start_date');
$table->date('end_date');
$table->string('status', 20)->default('active'); // 'active', 'completed', 'cancelled'
$table->timestamps();
$table->softDeletes();
```

## Migration Best Practices

1. **One concern per migration** — don't mix table creation with data seeding
2. **Always write `down()` methods** — enable clean rollbacks during development
3. **Never modify released migrations** — create new migrations to alter existing tables
4. **Test with `make fresh`** — ensure migrations run cleanly from scratch
5. **Seed after migrate** — use `make fresh` (migrate:fresh --seed) for full resets
