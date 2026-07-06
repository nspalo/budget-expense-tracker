<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maxLength = fake()->numberBetween(1, 100);
        $name = substr(fake()->sentence(), 0, $maxLength) ?: 'A';

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'type' => fake()->randomElement(AccountType::cases()),
            'balance_centavos' => fake()->numberBetween(0, 99_999_999_999),
        ];
    }
}
