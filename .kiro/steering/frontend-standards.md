---
inclusion: fileMatch
fileMatchPattern: "**/*.{vue,ts,tsx}"
---

# Frontend Standards

## Component Architecture

### Composition API Only
All components use `<script setup lang="ts">` — no Options API.

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

// 1. Props & emits (top)
// 2. Store/composable usage
// 3. Reactive state
// 4. Computed properties
// 5. Methods
// 6. Lifecycle hooks (bottom)
</script>

<template>
  <!-- Template -->
</template>
```

### Component Types

**Pages** (`pages/`) — Route-level views, handle data fetching and layout:
```
DashboardPage.vue
TransactionsPage.vue
BudgetsPage.vue
InstallmentsPage.vue
AccountsPage.vue
```

**Feature components** (`components/{domain}/`) — Domain-specific UI:
```
components/transaction/TransactionList.vue
components/transaction/TransactionForm.vue
components/transaction/TransactionRow.vue
components/budget/BudgetCard.vue
components/budget/BudgetProgress.vue
```

**Base components** (`components/common/`) — Reusable UI primitives:
```
components/common/BaseButton.vue
components/common/BaseInput.vue
components/common/BaseModal.vue
components/common/BaseCard.vue
components/common/BaseCurrencyInput.vue
```

## State Management (Pinia)

One store per domain using setup syntax:

```ts
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Transaction, TransactionFilter } from '@/types/transaction'
import { useGraphQL } from '@/composables/useGraphQL'

export const useTransactionStore = defineStore('transaction', () => {
  // State
  const transactions = ref<Transaction[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const totalExpenses = computed(() =>
    transactions.value
      .filter(t => t.type === 'expense')
      .reduce((sum, t) => sum + t.amount_centavos, 0)
  )

  // Actions
  async function fetchByFilter(filter: TransactionFilter): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await useGraphQL().query('transactions', { filter })
      transactions.value = data.transactions.data
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load transactions'
    } finally {
      isLoading.value = false
    }
  }

  return { transactions, isLoading, error, totalExpenses, fetchByFilter }
})
```

### Store Rules
- Stores own server state and handle API calls
- Components read from stores, dispatch actions to stores
- Don't put UI state (modal open/closed, form field values) in stores — keep that local
- Stores can call other stores for cross-domain reads

## Money Handling

### The Rule
- API sends/receives amounts in **pesos** (Float)
- Stores and types use **centavos** (integer) internally for precision
- Display formatting happens at the component/composable level

### Composable: `useMoney`
```ts
export function useMoney() {
  function formatPeso(centavos: number): string {
    return `₱${(centavos / 100).toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`
  }

  function toCentavos(pesos: number): number {
    return Math.round(pesos * 100)
  }

  function toPesos(centavos: number): number {
    return centavos / 100
  }

  return { formatPeso, toCentavos, toPesos }
}
```

### Currency Input Component
- Accept user input in pesos (decimal)
- Display with ₱ prefix and thousand separators
- Emit centavos to parent
- Validate: no negative values, max 2 decimal places

## Forms

### Pattern
- Form state is local (`ref` in the component)
- Validation uses a composable or library (e.g., VeeValidate or manual)
- Submit calls a store action
- Show loading state during submission
- Display server-side validation errors per field

```vue
<script setup lang="ts">
const form = ref({
  amount: '',
  type: 'expense' as TransactionType,
  payment_date: '',
  billing_period: '',
  category_id: null as number | null,
  account_id: null as number | null,
  description: '',
})

const errors = ref<Record<string, string>>({})
const isSubmitting = ref(false)

async function handleSubmit() {
  isSubmitting.value = true
  errors.value = {}
  try {
    await transactionStore.create({
      ...form.value,
      amount: useMoney().toCentavos(parseFloat(form.value.amount)),
    })
    emit('created')
  } catch (e) {
    if (e.validationErrors) errors.value = e.validationErrors
  } finally {
    isSubmitting.value = false
  }
}
</script>
```

## Error Handling

### API Errors
- Catch in store actions, set `error` ref
- Components display errors via conditional rendering
- Use a toast/notification system for transient errors
- Use inline field errors for validation failures

### Loading States
- Every async operation has an `isLoading` ref
- Show skeleton loaders or spinners during fetch
- Disable submit buttons during form submission
- Never show stale data without indicating it's refreshing

### Error State Type
```ts
type AsyncState<T> =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'success'; data: T }
  | { status: 'error'; message: string }
```

## GraphQL Client

Use a composable that wraps the GraphQL client:

```ts
export function useGraphQL() {
  async function query<T>(operationName: string, variables?: Record<string, unknown>): Promise<{ data: T }> {
    // Implementation wraps fetch or a lightweight GraphQL client
    // Automatically attaches auth token
    // Handles 401 → redirect to login
  }

  async function mutate<T>(operationName: string, variables: Record<string, unknown>): Promise<{ data: T }> {
    // Same as query but for mutations
  }

  return { query, mutate }
}
```

## Routing

- Vue Router with lazy-loaded page components
- Auth guard on all financial routes
- Public routes: login, register
- Route naming: kebab-case (`/transactions`, `/budgets`, `/installments/:id`)

## Styling (Tailwind CSS 4)

- Utility-first — compose styles directly in templates
- No custom CSS unless unavoidable (complex animations, third-party overrides)
- Consistent spacing scale: use Tailwind's default spacing tokens
- Color palette: define semantic colors in Tailwind config (primary, success, danger, warning)
- Responsive: mobile-first approach, breakpoints for tablet/desktop
- Dark mode: plan with `dark:` variants but implement after MVP

## Accessibility

- All form inputs have associated labels (visible or `sr-only`)
- Interactive elements are keyboard-navigable
- Use semantic HTML (`<button>`, `<nav>`, `<main>`, `<table>`)
- Color is not the only indicator of state (use icons + text)
- Aria attributes on dynamic content (modals, dropdowns, alerts)
- Focus management on modal open/close
- Currency values announced correctly by screen readers

## File Organization

```
resources/js/
├── app.ts
├── router/
│   └── index.ts
├── components/
│   ├── common/         # BaseButton, BaseInput, BaseModal, BaseCurrencyInput
│   ├── transaction/    # TransactionList, TransactionForm, TransactionRow
│   ├── budget/         # BudgetCard, BudgetProgress, BudgetForm
│   ├── dashboard/      # DashboardSummary, PeriodFilter, CashFlowChart
│   ├── installment/    # InstallmentPlanCard, InstallmentSchedule
│   └── account/        # AccountCard, AccountForm
├── pages/
├── stores/
├── composables/
│   ├── useMoney.ts
│   ├── useDateFilter.ts
│   ├── useGraphQL.ts
│   └── useAuth.ts
├── types/
│   ├── transaction.ts
│   ├── budget.ts
│   ├── account.ts
│   └── api.ts
└── utils/
    ├── money.ts
    └── dates.ts
```
