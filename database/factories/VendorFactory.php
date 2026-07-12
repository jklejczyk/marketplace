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
    private const CITY_ANCHORS = [
        [21.012, 52.230],  // Warszawa
        [19.945, 50.065],  // Kraków
        [18.646, 54.352],  // Gdańsk
        [17.038, 51.108],  // Wrocław
        [16.925, 52.406],  // Poznań
        [19.456, 51.759],  // Łódź
    ];

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
            'location' => $this->makeLocation(),
        ];
    }

    private function makeLocation(): array
    {
        [$lng, $lat] = fake()->randomElement(self::CITY_ANCHORS);

        return [
            'type' => 'Point',
            'coordinates' => [
                round($lng + fake()->randomFloat(4, -0.05, 0.05), 6),
                round($lat + fake()->randomFloat(4, -0.05, 0.05), 6),
            ],
        ];
    }
}
