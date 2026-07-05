<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9_999_999),
            'email' => fake()->unique()->companyEmail(),
            'description' => fake()->optional()->catchPhrase(),
            'rating' => fake()->randomFloat(1, 3, 5),
            'products_count' => 0,
            'active' => fake()->boolean(90),
        ];
    }
}
