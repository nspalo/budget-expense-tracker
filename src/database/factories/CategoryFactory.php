<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

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
            'category_group_id' => function (array $attributes): int {
                return CategoryGroup::factory()
                    ->create(['user_id' => $attributes['user_id']])
                    ->id;
            },
            'name' => $name,
            'type' => fake()->randomElement(CategoryType::cases()),
            'sort_order' => fake()->numberBetween(0, 99),
            'icon' => null,
            'color' => null,
        ];
    }
}
