---
inclusion: fileMatch
fileMatchPattern: "**/*.php"
---

# Backend Standards

## Architecture Layers

```
Request → Controller/Resolver → Service → Repository/Model → Database
                                   ↕
                              DTO / Enum
```

Each layer has a single responsibility. Data flows down through the layers, and responses flow back up.

## Controllers (Thin Orchestrators)

- Accept the request, delegate to a service, return the response
- No business logic, no direct model queries, no data transformation
- One public method per controller in single-action controllers, or RESTful resource methods
- Use Form Requests for validation (injected automatically)

```php
final class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    public function store(StoreTransactionRequest $request): TransactionResource
    {
        $dto = TransactionData::fromRequest($request);
        $transaction = $this->transactionService->create($dto);

        return new TransactionResource($transaction);
    }
}
```

## Services (Business Logic)

- Contain all business rules and orchestration
- One service per domain: `TransactionService`, `BudgetService`, `InstallmentService`
- Services can call other services for cross-domain operations
- Services call repositories for complex queries, or models directly for simple CRUD
- Wrap multi-step operations in database transactions
- Throw domain-specific exceptions for business rule violations

```php
final class TransactionService
{
    public function __construct(
        private readonly TransactionRepository $repository,
        private readonly AccountService $accountService,
    ) {}

    public function create(TransactionData $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $transaction = Transaction::create($data->toArray());
            $this->accountService->updateBalance($data->accountId, $data->amountCentavos, $data->type);

            return $transaction;
        });
    }
}
```

## Repositories (Complex Data Access)

- Encapsulate complex queries that don't fit naturally on a model scope
- Return Eloquent models or collections (not arrays or raw query results)
- Use when: multi-join queries, aggregations, reporting, queries with dynamic conditions
- Skip when: simple `findOrFail()`, `create()`, `where()->get()` — use the model directly

```php
final class TransactionRepository
{
    public function getByBillingPeriod(string $period, ?int $categoryId = null): Collection
    {
        return Transaction::query()
            ->with(['category', 'account'])
            ->where('billing_period', $period)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('payment_date')
            ->get();
    }

    public function sumByCategory(string $period): Collection
    {
        return Transaction::query()
            ->where('billing_period', $period)
            ->where('type', TransactionType::Expense)
            ->selectRaw('category_id, SUM(amount_centavos) as total_centavos')
            ->groupBy('category_id')
            ->get();
    }
}
```

## DTOs (Data Transfer Objects)

- Use for passing structured data between layers
- Immutable — all properties are `readonly`
- Created from request data via a static factory method
- Convert centavos at the DTO boundary (request sends pesos, DTO stores centavos)

```php
final readonly class TransactionData
{
    public function __construct(
        public int $amountCentavos,
        public TransactionType $type,
        public string $paymentDate,
        public string $billingPeriod,
        public int $accountId,
        public ?int $categoryId,
        public ?string $description,
    ) {}

    public static function fromRequest(StoreTransactionRequest $request): self
    {
        return new self(
            amountCentavos: (int) round($request->float('amount') * 100),
            type: TransactionType::from($request->string('type')),
            paymentDate: $request->string('payment_date'),
            billingPeriod: $request->string('billing_period'),
            accountId: $request->integer('account_id'),
            categoryId: $request->integer('category_id') ?: null,
            description: $request->string('description') ?: null,
        );
    }

    public function toArray(): array
    {
        return [
            'amount_centavos' => $this->amountCentavos,
            'type' => $this->type,
            'payment_date' => $this->paymentDate,
            'billing_period' => $this->billingPeriod,
            'account_id' => $this->accountId,
            'category_id' => $this->categoryId,
            'description' => $this->description,
        ];
    }
}
```

## Enums

- Use PHP 8.1+ backed enums for all finite value sets
- Store as strings in the database (not MySQL ENUM type)
- Cast in model `$casts` for automatic hydration
- Use in Form Request validation rules

```php
enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
}

enum AccountType: string
{
    case BankAccount = 'bank_account';
    case DebitCard = 'debit_card';
    case EWallet = 'e_wallet';
    case CreditCard = 'credit_card';
    case Cash = 'cash';
}

enum InstallmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

enum Frequency: string
{
    case Monthly = 'monthly';
    case BiWeekly = 'bi-weekly';
    case Weekly = 'weekly';
}
```

## API Resources (Response Transformation)

- One resource per model for consistent API output
- Handle centavos → pesos conversion for display
- Conditionally include relationships with `whenLoaded()`
- Never expose raw database columns — shape the response intentionally

```php
final class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount_centavos / 100,
            'type' => $this->type->value,
            'description' => $this->description,
            'payment_date' => $this->payment_date->toDateString(),
            'billing_period' => $this->billing_period,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'account' => AccountResource::make($this->whenLoaded('account')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

## Form Requests (Validation)

- One request class per action: `StoreTransactionRequest`, `UpdateBudgetRequest`
- Authorization logic in `authorize()` — verify ownership of related resources
- Prepare input in `prepareForValidation()` for data normalization
- Use enum validation: `Rule::enum(TransactionType::class)`

```php
final class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware, ownership by global scope
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'payment_date' => ['required', 'date'],
            'billing_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'account_id' => ['required', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

## Action Pattern (Single-Purpose Classes)

Use Actions for one-off operations that don't fit cleanly in a service:
- Complex operations with many side effects
- Operations shared between multiple entry points (CLI, API, queue job)

```php
final class GenerateInstallmentPayment
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    public function execute(InstallmentPlan $plan): Transaction
    {
        $transaction = $this->transactionService->create(
            new TransactionData(
                amountCentavos: $plan->installment_amount_centavos,
                type: TransactionType::Expense,
                paymentDate: now()->toDateString(),
                billingPeriod: now()->format('Y-m'),
                accountId: $plan->account_id,
                categoryId: $plan->category_id,
                description: "Installment {$plan->completed_installments + 1}/{$plan->total_installments}: {$plan->name}",
            )
        );

        $plan->increment('completed_installments');

        if ($plan->completed_installments >= $plan->total_installments) {
            $plan->update(['status' => InstallmentStatus::Completed]);
        }

        return $transaction;
    }
}
```

## Factories (Test Data)

- Every model MUST have a factory
- Factories produce realistic data using Faker
- Define states for common variations
- Money values in factories are always centavos

```php
final class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount_centavos' => fake()->numberBetween(1000, 500000), // ₱10 to ₱5,000
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'billing_period' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m'),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function expense(): static
    {
        return $this->state(['type' => TransactionType::Expense]);
    }

    public function income(): static
    {
        return $this->state(['type' => TransactionType::Income]);
    }

    public function forPeriod(string $period): static
    {
        return $this->state(['billing_period' => $period]);
    }
}
```

## Error Handling

- Throw domain exceptions from services: `InsufficientBudgetException`, `InvalidBillingPeriodException`
- Map exceptions to HTTP status codes in the exception handler
- Never expose internal details (stack traces, SQL queries) in API responses
- Log all financial operation failures with context (user, amount, operation)
- Use `report()` for background logging, `render()` for response shaping

## Model Traits

Shared behavior across financial models:

```php
// Data isolation
trait BelongsToAuthenticatedUser { ... }

// Audit trail via model events
trait HasAuditTrail { ... }

// Money accessor/mutator helpers
trait FormatsMoneyAttribute { ... }
```
