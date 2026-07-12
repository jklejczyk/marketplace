<?php

use App\Actions\Vendors\VendorsInArea;
use App\Models\Vendor;

beforeEach(function () {
    Vendor::truncate();
    Vendor::raw(fn ($collection) => $collection->createIndex(['location' => '2dsphere']));
});

function placeVendor(string $name, float $lng, float $lat): Vendor
{
    return Vendor::factory()->create([
        'name' => $name,
        'location' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
    ]);
}

function warsawSquare(): array
{
    return [
        [20.9, 52.15],
        [21.1, 52.15],
        [21.1, 52.30],
        [20.9, 52.30],
    ];
}

test('zwraca vendorów wewnątrz wielokąta, wyklucza zewnętrznych (otwarty pierścień domykany)', function () {
    placeVendor('W środku', 21.00, 52.23);   // w kwadracie
    placeVendor('Kraków', 19.945, 50.065);   // poza

    $result = (new VendorsInArea)->handle(warsawSquare());

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('W środku');
});

test('$geoWithin korzysta z indeksu 2dsphere', function () {
    placeVendor('W środku', 21.00, 52.23);

    $explain = DB::connection('mongodb')->getDatabase()->command([
        'explain' => ['find' => 'vendors', 'filter' => [
            'location' => ['$geoWithin' => ['$geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[...warsawSquare(), [20.9, 52.15]]],
            ]]],
        ]],
        'verbosity' => 'queryPlanner',
    ])->toArray()[0];

    $plan = $explain['queryPlanner']['winningPlan'];

    expect($plan['inputStage']['indexName'])->toBe('location_2dsphere');
});
