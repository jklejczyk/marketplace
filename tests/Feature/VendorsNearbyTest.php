<?php

use App\Actions\Vendors\VendorsNearby;
use App\Models\Vendor;

beforeEach(function () {
    Vendor::truncate();
    Vendor::raw(fn ($collection) => $collection->createIndex(['location' => '2dsphere']));
});

function vendorAt(string $name, float $lng, float $lat): Vendor
{
    return Vendor::factory()->create([
        'name' => $name,
        'location' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
    ]);
}

test('zwraca vendorów w promieniu, sortuje rosnąco po dystansie, wyklucza dalekich', function () {
    vendorAt('Blisko', 21.001, 52.231);
    vendorAt('Dalej', 21.030, 52.240);
    vendorAt('Kraków', 19.945, 50.065); // poza 5 km

    $result = (new VendorsNearby)->handle(21.00, 52.23, 5000);

    expect($result)->toHaveCount(2)
        ->and(collect($result)->pluck('name')->all())->toBe(['Blisko', 'Dalej']);

    expect($result[0]['distance_m'])->toBeLessThan($result[1]['distance_m'])
        ->and($result[0]['distance_m'])->toBeLessThan(1000.0);
});

test('$near korzysta z indeksu 2dsphere (GEO_NEAR_2DSPHERE), nie COLLSCAN', function () {
    vendorAt('X', 21.001, 52.231);

    $explain = DB::connection('mongodb')->getDatabase()->command([
        'explain' => ['find' => 'vendors', 'filter' => [
            'location' => ['$near' => [
                '$geometry' => ['type' => 'Point', 'coordinates' => [21.00, 52.23]],
            ]],
        ]],
        'verbosity' => 'queryPlanner',
    ])->toArray()[0];

    $plan = $explain['queryPlanner']['winningPlan'];

    expect($plan['stage'])->toBe('GEO_NEAR_2DSPHERE');
});
