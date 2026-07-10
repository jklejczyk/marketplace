<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Pula userów ładowana raz na proces (sqlite) — jak w OrderFactory.
     *
     * @var Collection<int, User>|null
     */
    private static ?Collection $userPool = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $users = self::$userPool ??= User::query()->get(['id', 'name']);
        $user = $users->random();

        return [
            'product_id' => null,
            'user_id' => $user->id,
            'user_snapshot' => [
                'name' => $user->name,
            ],
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'comment' => fake()->paragraph(),
            'created_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'product_id' => (string) $product->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'user_snapshot' => ['name' => $user->name],
        ]);
    }
}
