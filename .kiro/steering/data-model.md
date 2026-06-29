---
inclusion: manual
---

# Data Model Reference

## Entity Relationship Overview

```
┌──────────┐       ┌──────────────┐       ┌────────────┐
│  users   │───┐   │ transactions │   ┌───│ categories │
└──────────┘   │   └──────────────┘   │   └────────────┘
               │          │            │
               │          │ belongs_to │
               │          ├────────────┘
               │          │
               │          │ belongs_to
               │          ├────────────┐
               │          │            │
               │   ┌──────────────┐   │   ┌──────────┐
               ├───│   accounts   │   └───│ budgets  │
               │   └──────────────┘       └──────────┘
               │
               │   ┌───────────────────┐
               └───│ installment_plans │
                   └───────────────────┘
```

## Relationships

### User (1:many everything)
- has many Accounts
- has many Categories
- has many Transactions
- has many Budgets
- has many InstallmentPlans

### Account
- belongs to User
- has many Transactions
- has many InstallmentPlans

### Category
- belongs to User
- has many Transactions
- has many Budgets
- type: 'expense' | 'income' (determines which transactions it can be linked to)

### Transaction (the core ledger entry)
- belongs to User
- belongs to Account
- belongs to Category (nullable for transfers)
- belongs to InstallmentPlan (nullable, only if generated from an installment)
- type: 'income' | 'expense' | 'transfer'

### Budget
- belongs to User
- belongs to Category
- unique constraint: one budget per user+category+period
- represents: "I want to spend at most X on this category in this month"

### InstallmentPlan
- belongs to User
- belongs to Account
- belongs to Category (nullable)
- has many Transactions (the generated installment payments)
- status: 'active' | 'completed' | 'cancelled'

## Dual-Axis Query Patterns

### Accrual View (billing_period)
"How much did I spend in June 2026?"
```sql
SELECT category_id, SUM(amount_centavos)
FROM transactions
WHERE user_id = ? AND billing_period = '2026-06' AND type = 'expense'
GROUP BY category_id
```

### Cash Flow View (payment_date)
"How much money left my accounts this pay cycle?"
```sql
SELECT account_id, SUM(amount_centavos)
FROM transactions
WHERE user_id = ? AND payment_date BETWEEN '2026-06-01' AND '2026-06-15' AND type = 'expense'
GROUP BY account_id
```

### Budget vs Actual
"How am I tracking against my budget targets for June?"
```sql
SELECT b.category_id, b.target_amount_centavos,
       COALESCE(SUM(t.amount_centavos), 0) as spent_centavos
FROM budgets b
LEFT JOIN transactions t ON t.category_id = b.category_id
  AND t.billing_period = b.period
  AND t.user_id = b.user_id
  AND t.type = 'expense'
  AND t.deleted_at IS NULL
WHERE b.user_id = ? AND b.period = '2026-06'
GROUP BY b.category_id, b.target_amount_centavos
```

## Soft Delete Implications

- All financial queries MUST include soft delete awareness
- Eloquent handles this automatically via `SoftDeletes` trait
- Raw queries (reporting) must add `AND deleted_at IS NULL`
- Trashed records are still visible in audit trail views

## Currency

- All `_centavos` fields store Philippine Peso subunits
- 1 PHP = 100 centavos
- `accounts.currency` column exists for future multi-currency support
- No conversion logic implemented until explicitly needed
