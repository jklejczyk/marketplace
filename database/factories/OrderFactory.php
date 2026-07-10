<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Pule ładowane raz na proces. MongoDB nie wspiera inRandomOrder()
     * (to orderByRaw), a odpytywanie bazy per zamówienie byłoby zabójcze
     * przy seedowaniu — losujemy w pamięci.
     *
     * @var Collection<int, Product>|null
     */
    private static ?Collection $productPool = null;

    /** @var Collection<int, User>|null */
    private static ?Collection $userPool = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $products = self::$productPool ??= Product::query()->get(['_id', 'name', 'variants', 'vendor_id', 'vendor_name']);
        $users = self::$userPool ??= User::query()->get(['id', 'name', 'email']);

        $picked = $products->random(min(fake()->numberBetween(1, 4), $products->count()));
        $user = $users->random();

        $items = $picked->map(fn (Product $product): array => $this->makeItem($product))->all();

        return [
            'user_id' => $user->id,
            'user_snapshot' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'items' => $items,
            'total' => new Decimal128($this->sumItems($items)),
            'created_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'user_snapshot' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Snapshot produktu i wybranego wariantu w chwili zakupu.
     *
     * @return array<string, mixed>
     */
    protected function makeItem(Product $product): array
    {
        $variant = fake()->randomElement($product->variants);

        return [
            'product_id' => new ObjectId((string) $product->id),
            'name_snapshot' => $product->name,
            'price_snapshot' => new Decimal128((string) $variant['price']),
            'variant_snapshot' => [
                'sku' => $variant['sku'],
                'size' => $variant['size'],
                'color' => $variant['color'],
            ],
            'vendor_id' => new ObjectId((string) $product->vendor_id),
            'vendor_name' => $product->vendor_name,
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }

    /**
     * Suma Σ(price_snapshot × quantity) liczona na stringach (bcmath), nigdy przez float.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function sumItems(array $items): string
    {
        return array_reduce($items, fn (string $carry, array $item): string => bcadd(
            $carry,
            bcmul((string) $item['price_snapshot'], (string) $item['quantity'], 2),
            2
        ), '0');
    }
}
