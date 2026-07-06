<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\User;
use App\Services\CategoryGroupService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DatabaseSeeder extends Seeder
{
    private const DEMO_EMAIL = 'demo@budgettracker.test';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::where('email', self::DEMO_EMAIL)->exists()) {
            return;
        }

        // Create user without model events to avoid observer firing without auth context
        $user = User::withoutEvents(function () {
            return User::factory()->create([
                'name' => 'Demo User',
                'email' => self::DEMO_EMAIL,
            ]);
        });

        // Authenticate as the demo user so audit trail and global scopes work correctly
        Auth::login($user);

        // Create default category groups (normally done by UserObserver)
        app(CategoryGroupService::class)->createDefaults($user);

        $this->createAccounts();
        $this->createCategories();

        Auth::logout();
    }

    /**
     * Create five accounts (one per AccountType) for the demo user.
     */
    private function createAccounts(): void
    {
        $accounts = [
            ['name' => 'BPI Savings', 'type' => AccountType::BankAccount, 'balance_centavos' => 150_000_00],
            ['name' => 'BPI Debit', 'type' => AccountType::DebitCard, 'balance_centavos' => 45_000_00],
            ['name' => 'GCash', 'type' => AccountType::EWallet, 'balance_centavos' => 5_000_00],
            ['name' => 'BDO Credit Card', 'type' => AccountType::CreditCard, 'balance_centavos' => 25_000_00],
            ['name' => 'Wallet', 'type' => AccountType::Cash, 'balance_centavos' => 2_500_00],
        ];

        foreach ($accounts as $account) {
            Account::create([
                'name' => $account['name'],
                'type' => $account['type'],
                'balance_centavos' => $account['balance_centavos'],
            ]);
        }
    }

    /**
     * Create categories distributed across the four default groups.
     *
     * Both expense and income types are represented across the full set.
     */
    private function createCategories(): void
    {
        $groups = CategoryGroup::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('name');

        $categories = [
            // Needs group — 3 categories
            ['group' => 'Needs', 'name' => 'Electricity', 'type' => CategoryType::Expense, 'sort_order' => 1],
            ['group' => 'Needs', 'name' => 'Water', 'type' => CategoryType::Expense, 'sort_order' => 2],
            ['group' => 'Needs', 'name' => 'Salary', 'type' => CategoryType::Income, 'sort_order' => 3],

            // Wants group — 2 categories
            ['group' => 'Wants', 'name' => 'Food & Dining', 'type' => CategoryType::Expense, 'sort_order' => 1],
            ['group' => 'Wants', 'name' => 'Entertainment', 'type' => CategoryType::Expense, 'sort_order' => 2],

            // Savings group — 2 categories
            ['group' => 'Savings', 'name' => 'Emergency Fund', 'type' => CategoryType::Expense, 'sort_order' => 1],
            ['group' => 'Savings', 'name' => 'Investment Returns', 'type' => CategoryType::Income, 'sort_order' => 2],

            // Others group — 2 categories
            ['group' => 'Others', 'name' => 'Transport', 'type' => CategoryType::Expense, 'sort_order' => 1],
            ['group' => 'Others', 'name' => 'Freelance', 'type' => CategoryType::Income, 'sort_order' => 2],
        ];

        foreach ($categories as $category) {
            Category::create([
                'category_group_id' => $groups[$category['group']]->id,
                'name' => $category['name'],
                'type' => $category['type'],
                'sort_order' => $category['sort_order'],
            ]);
        }
    }
}
