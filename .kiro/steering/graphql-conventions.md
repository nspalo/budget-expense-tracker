---
inclusion: fileMatch
fileMatchPattern: "**/*.graphql"
---

# GraphQL Conventions

## Library: Lighthouse PHP

This project uses [Lighthouse](https://lighthouse-php.com/) as the GraphQL server for Laravel. It provides schema-first development with directives that map directly to Eloquent.

## Schema Location

```
src/
├── graphql/
│   ├── schema.graphql          # Root schema (imports all type files)
│   ├── types/
│   │   ├── transaction.graphql
│   │   ├── account.graphql
│   │   ├── category.graphql
│   │   ├── budget.graphql
│   │   └── installment.graphql
│   ├── queries/
│   │   ├── transaction.graphql
│   │   └── budget.graphql
│   └── mutations/
│       ├── transaction.graphql
│       └── budget.graphql
```

## Schema Design Rules

### Types mirror models, not database tables
- Expose `amount` as a formatted Float (pesos) in the GraphQL type, but store as integer (centavos) internally
- Use a custom scalar or resolver to handle the centavo ↔ peso conversion at the boundary
- Never expose internal IDs without purpose; use the model's `id` field

### Naming
- Types: PascalCase singular (`Transaction`, `BudgetTarget`)
- Queries: camelCase (`transactions`, `budgetsByPeriod`)
- Mutations: camelCase verb-first (`createTransaction`, `updateBudget`, `deleteTransaction`)
- Input types: `{Action}{Type}Input` (`CreateTransactionInput`, `UpdateBudgetInput`)
- Enums: SCREAMING_SNAKE_CASE values (`EXPENSE`, `INCOME`, `TRANSFER`)

### Pagination
- Use Lighthouse's built-in `@paginate` directive for list queries
- Default page size: 25, max: 100
- Support both offset and cursor-based pagination

### Filtering
- Use dedicated input types for filters: `TransactionFilter`
- Support dual-axis filtering:
  - `billingPeriod: String` — filter by accrual month (e.g., "2026-06")
  - `paymentDateFrom: Date` / `paymentDateTo: Date` — filter by cash flow range
  - `categoryId: ID` — filter by category
  - `accountId: ID` — filter by account

### Example Schema Pattern

```graphql
type Transaction {
  id: ID!
  amount: Float!
  type: TransactionType!
  description: String
  payment_date: Date!
  billing_period: String!
  category: Category! @belongsTo
  account: Account! @belongsTo
  installment_plan: InstallmentPlan @belongsTo
  created_at: DateTime!
  updated_at: DateTime!
}

enum TransactionType {
  INCOME
  EXPENSE
  TRANSFER
}

input TransactionFilter {
  billing_period: String
  payment_date_from: Date
  payment_date_to: Date
  category_id: ID
  account_id: ID
  type: TransactionType
}

type Query {
  transactions(filter: TransactionFilter @spread): [Transaction!]! @paginate @guard
  transaction(id: ID! @eq): Transaction @find @guard
}

input CreateTransactionInput {
  amount: Float!
  type: TransactionType!
  description: String
  payment_date: Date!
  billing_period: String!
  category_id: ID!
  account_id: ID!
}

type Mutation {
  createTransaction(input: CreateTransactionInput! @spread): Transaction! @guard
  updateTransaction(id: ID!, input: UpdateTransactionInput! @spread): Transaction! @guard
  deleteTransaction(id: ID!): Transaction! @guard @softDeletes
}
```

## Resolvers

- Use Lighthouse directives (`@all`, `@find`, `@paginate`, `@create`, `@update`) for simple CRUD
- For complex business logic (installment generation, budget calculations), use custom resolvers in `app/GraphQL/`
- Custom resolvers go in:
  - `app/GraphQL/Queries/` — custom query resolvers
  - `app/GraphQL/Mutations/` — custom mutation resolvers

## Authorization

- Use `@guard` directive on all queries and mutations (requires authenticated user)
- Use `@can` directive or policy checks for resource-level authorization
- All queries are automatically scoped to the authenticated user via global scopes on models

## Money Conversion at the Boundary

The GraphQL layer is where centavos convert to pesos for external consumers:
- **Input:** API receives pesos (Float) → mutation resolver converts to centavos before saving
- **Output:** Database stores centavos → type resolver converts to pesos for response
- This keeps the API consumer-friendly while maintaining integer precision internally

## Error Handling

- Return structured GraphQL errors with appropriate extensions
- Use Lighthouse's validation directives (`@rules`) for input validation
- Business logic errors should use custom exceptions that map to GraphQL error responses
