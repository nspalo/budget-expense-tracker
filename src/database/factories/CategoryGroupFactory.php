<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CategoryGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryGroup>
 */
class CategoryGroupFactory extends Factory
{
    protected $model = CategoryGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maxLength = fake()->numberBetween(1, 50);
        $name = substr(fake()->sentence(), 0, $maxLength) ?: 'A';

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'sort_order' => fake()->numberBetween(0, 99),
        ];
    }
}
