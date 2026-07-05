<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use MongoDB\BSON\Decimal128;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->words(fake()->numberBetween(2, 4), true));
        $price = fake()->randomFloat(2, 15, 500);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9_999_999),
            'description' => fake()->paragraph(),
            'price' => $price,

            'vendor_id' => null,
            'vendor_name' => null,
            'category_id' => null,
            'category_path' => [],

            'variants' => $this->makeVariants($price),
            'tags' => fake()->randomElements(
                ['zima', 'lato', 'unisex', 'premium', 'wyprzedaż', 'nowość', 'eko', 'limitowana'],
                fake()->numberBetween(1, 3)
            ),
            'attributes' => [
                'material' => fake()->randomElement(['bawełna', 'poliester', 'wełna', 'len', 'skóra']),
                'kraj' => fake()->randomElement(['PL', 'DE', 'IT', 'CN']),
            ],

            'avg_rating' => 0,
            'reviews_count' => 0,
            'active' => fake()->boolean(85),
        ];
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (): array => [
            'vendor_id' => (string) $vendor->id,
            'vendor_name' => $vendor->name,
        ]);
    }

    public function inCategory(Category $leaf, array $path): static
    {
        return $this->state(fn (): array => [
            'category_id' => (string) $leaf->id,
            'category_path' => $path,
        ]);
    }

    protected function makeVariants(float $basePrice): array
    {
        $sizes = fake()->randomElements(['XS', 'S', 'M', 'L', 'XL'], fake()->numberBetween(1, 4));
        $color = fake()->safeColorName();

        return array_map(fn (string $size): array => [
            'sku' => strtoupper(Str::random(3)).'-'.$size.'-'.strtoupper(substr($color, 0, 3)),
            'size' => $size,
            'color' => $color,
            'stock' => fake()->numberBetween(0, 50),
            'price' => new Decimal128((string) $basePrice),
        ], $sizes);
    }
}
