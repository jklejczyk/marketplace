<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use MongoDB\BSON\ObjectId;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    private static ?Collection $productPool = null;

    /** @var Collection<int, User>|null */
    private static ?Collection $userPool = null;

    public function definition(): array
    {
        $products = self::$productPool ??= Product::query()->get(['_id', 'variants']);
        $users = self::$userPool ??= User::query()->get(['id']);

        $picked = $products->random(min(fake()->numberBetween(1, 4), $products->count()));

        $items = $picked->map(fn (Product $product): array => $this->makeItem($product))->all();

        return [
            'user_id' => $users->random()->id,
            'items' => $items,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    protected function makeItem(Product $product): array
    {
        $variant = fake()->randomElement($product->variants);

        return [
            'product_id' => new ObjectId((string) $product->id),
            'variant_sku' => $variant['sku'],
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }
}
