---
inclusion: manual
---

# API Reference

## GraphQL Endpoint

- **URL:** `/graphql`
- **Auth:** Bearer token (Laravel Sanctum)
- **Playground:** `/graphiql` (dev only)

## Authentication

All queries and mutations require authentication via the `@guard` directive.
Token-based auth using Laravel Sanctum. Login/register may use REST endpoints or dedicated GraphQL mutations.

## Queries

### transactions
Paginated list of transactions for the authenticated user.

```graphql
query {
  transactions(
    filter: {
      billing_period: "2026-06"
      type: EXPENSE
    }
    first: 25
    page: 1
  ) {
    data {
      id
      amount
      type
      description
      payment_date
      billing_period
      category { id name icon color }
      account { id name type }
    }
    paginatorInfo {
      currentPage
      lastPage
      total
    }
  }
}
```

### budgets
Budget targets with actual spending for a given period.

```graphql
query {
  budgets(period: "2026-06") {
    id
    target_amount
    spent_amount
    remaining_amount
    percentage_used
    category { id name icon color }
  }
}
```

### accounts
User's financial accounts with balances.

```graphql
query {
  accounts {
    id
    name
    type
    balance
    is_active
    currency
  }
}
```

### installmentPlans
Active and completed installment plans.

```graphql
query {
  installmentPlans(status: ACTIVE) {
    id
    name
    total_amount
    installment_amount
    total_installments
    completed_installments
    frequency
    start_date
    end_date
    status
    category { id name }
    account { id name }
  }
}
```

### dashboardSummary
Aggregated data for the main dashboard.

```graphql
query {
  dashboardSummary(
    billing_period: "2026-06"
    payment_date_from: "2026-06-01"
    payment_date_to: "2026-06-30"
  ) {
    total_income
    total_expenses
    net_cash_flow
    budget_utilization_percentage
    top_categories {
      category { name color }
      amount
      percentage
    }
  }
}
```

## Mutations

### createTransaction
```graphql
mutation {
  createTransaction(input: {
    amount: 1500.50
    type: EXPENSE
    description: "Electric bill - June"
    payment_date: "2026-07-05"
    billing_period: "2026-06"
    category_id: "3"
    account_id: "1"
  }) {
    id
    amount
    billing_period
    payment_date
  }
}
```

Note: `amount` is in pesos (Float). The server converts to centavos for storage.

### updateTransaction
```graphql
mutation {
  updateTransaction(id: "42", input: {
    amount: 1600.00
    description: "Electric bill - June (adjusted)"
  }) {
    id
    amount
    description
  }
}
```

### deleteTransaction
Soft deletes the transaction.
```graphql
mutation {
  deleteTransaction(id: "42") {
    id
  }
}
```

### createBudget / updateBudget
```graphql
mutation {
  createBudget(input: {
    category_id: "3"
    period: "2026-07"
    target_amount: 5000.00
  }) {
    id
    target_amount
    category { name }
  }
}
```

### createInstallmentPlan
```graphql
mutation {
  createInstallmentPlan(input: {
    name: "Laptop - 12 months"
    total_amount: 48000.00
    total_installments: 12
    frequency: MONTHLY
    start_date: "2026-07-01"
    account_id: "1"
    category_id: "5"
  }) {
    id
    name
    installment_amount
    end_date
  }
}
```

## Error Response Format

```json
{
  "errors": [
    {
      "message": "Validation failed",
      "extensions": {
        "category": "validation",
        "errors": {
          "amount": ["The amount must be greater than 0."]
        }
      }
    }
  ]
}
```

## Money Convention (API Boundary)

- **Request:** amounts in pesos as Float (e.g., `1500.50`)
- **Response:** amounts in pesos as Float (e.g., `1500.50`)
- **Storage:** amounts in centavos as Integer (e.g., `150050`)
- Conversion happens in GraphQL resolvers / mutators, transparent to API consumers
