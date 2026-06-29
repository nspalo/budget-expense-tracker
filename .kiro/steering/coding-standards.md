---
inclusion: always
---

# Coding Standards

## PSR Compliance

This project follows PSR-12 (Extended Coding Style) for all PHP code:
- 4 spaces indentation (no tabs)
- One class per file
- Opening braces on the same line for control structures, next line for classes/methods
- One blank line after namespace, after use block, before/after methods
- Visibility declared on all methods and properties
- No closing `?>` tag

Use Laravel Pint for automated formatting: `make composer cmd="run pint"`

## SOLID Principles

### Single Responsibility
- Each class has one reason to change
- Services handle one domain: `TransactionService` doesn't calculate budgets
- Controllers only orchestrate request→service→response
- Avoid "God classes" — split when a class exceeds ~200 lines or handles multiple concerns

### Open/Closed
- Extend behavior through composition, interfaces, and strategy patterns — not by modifying existing classes
- Use Laravel's event system to add side effects without modifying core logic
- Example: Adding audit logging should be an event listener, not code inside the service

### Liskov Substitution
- Subtypes must be substitutable for their base types
- If using interfaces (e.g., `TransactionRepositoryInterface`), all implementations must honor the contract
- Avoid throwing unexpected exceptions in implementations

### Interface Segregation
- Prefer small, focused interfaces over large ones
- A repository interface should only declare methods that all implementations need
- If a method is only needed by one implementation, it doesn't belong in the interface

### Dependency Inversion
- High-level modules depend on abstractions, not concretions
- Inject interfaces in constructors, bind in service providers
- Services depend on repository interfaces, not Eloquent models directly (for complex queries)
- Simple CRUD can use models directly — don't over-abstract

## Composition Over Inheritance

- Prefer traits and composition for shared behavior across unrelated classes
- Use traits for: `HasAuditTrail`, `BelongsToUser`, `FormatsMoneyAttribute`
- Use service injection over base controller methods
- Exception: Eloquent model inheritance is acceptable for STI patterns (if ever needed)
- Never go deeper than one level of class inheritance in application code

## KISS (Keep It Simple)

- Choose the simplest solution that works correctly
- Don't add abstraction layers "for future use" — add them when needed
- A direct Eloquent query in a service is fine until the query gets complex enough to warrant a repository
- Avoid design patterns for their own sake — use them when they solve a real problem
- If a method needs a comment to explain what it does, rename it or simplify it

## DRY (Don't Repeat Yourself)

- Extract repeated logic into services, traits, or helper methods
- BUT don't force unrelated code into the same abstraction just because it looks similar
- "Rule of three" — tolerate duplication twice, extract on the third occurrence
- Shared constants belong in Enums or config, not magic strings scattered across files
- Database queries that appear in multiple places belong in a repository method

## Type Safety

### PHP
- `declare(strict_types=1)` in every file
- Type hints on all method parameters and return types
- Use union types (`int|null`) over mixed
- Use PHP 8.1+ Enums for finite value sets (transaction types, account types, statuses)
- Avoid `mixed` type — be explicit about what a function accepts and returns

### TypeScript
- Strict mode enabled in `tsconfig.json`
- No `any` type — use `unknown` and narrow with type guards
- Define interfaces for all API response shapes
- Use discriminated unions for state machines (loading/success/error)

## Naming Conventions

### PHP
- Classes: `PascalCase` (`TransactionService`, `BudgetRepository`)
- Methods: `camelCase` (`calculateMonthlyTotal`, `getByBillingPeriod`)
- Properties/variables: `camelCase` (`$amountCentavos`, `$billingPeriod`)
- Constants/Enums: `PascalCase` for enum cases (`TransactionType::Expense`)
- Database columns: `snake_case` (`payment_date`, `amount_centavos`)

### TypeScript/Vue
- Components: `PascalCase` files and tags (`TransactionList.vue`)
- Functions/variables: `camelCase` (`formatCentavos`, `isLoading`)
- Types/Interfaces: `PascalCase` (`Transaction`, `BudgetSummary`)
- Constants: `SCREAMING_SNAKE_CASE` (`MAX_PAGE_SIZE`, `DEFAULT_CURRENCY`)
- Composables: `useCamelCase` (`useMoney`, `useDateFilter`)

## Code Organization

- Group by domain/feature, not by type, when the codebase grows
- Related files stay close: `TransactionService` near `TransactionRepository`
- Tests mirror source structure: `tests/Feature/Transaction/`, `tests/Unit/Services/`
- Keep file length manageable: split at ~300 lines for classes, ~200 for components
