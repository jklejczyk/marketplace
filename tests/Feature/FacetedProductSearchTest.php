<?php

use App\Actions\Products\FacetedProductSearch;
use App\Models\Product;
use MongoDB\BSON\Decimal128;

beforeEach(function () {
    Product::truncate();
});

function phone(string $vendor, float $price, string $size, bool $active = true): void
{
    Product::factory()->create([
        'active' => $active,
        'category_path' => ['Elektronika', 'Telefony'],
        'vendor_name' => $vendor,
        'price' => $price,
        'variants' => [['sku' => 'PH-'.$size, 'size' => $size, 'stock' => 5, 'price' => new Decimal128((string) $price)]],
    ]);
}

test('liczy countery facetów tylko dla pasujących produktów', function () {
    phone('Alpha', 30, 'M');
    phone('Alpha', 70, 'M');
    phone('Beta', 150, 'L');

    phone('Alpha', 40, 'M', active: false);
    Product::factory()->create([
        'active' => true,
        'category_path' => ['Elektronika', 'Laptopy'],
        'price' => 40,
    ]);

    $result = (new FacetedProductSearch)->handle(['category' => 'Telefony']);

    expect($result['meta'][0]['total'])->toBe(3);

    $byVendor = collect($result['byVendor'])->pluck('count', '_id');
    expect($byVendor['Alpha'])->toBe(2)
        ->and($byVendor['Beta'])->toBe(1);

    $byPrice = collect($result['byPrice'])->mapWithKeys(
        fn ($b) => [(string) $b['_id'] => $b['count']]
    );
    expect($byPrice['0'])->toBe(1)
        ->and($byPrice['50'])->toBe(1)
        ->and($byPrice['100'])->toBe(1);

    $bySize = collect($result['bySize'])->pluck('count', '_id');
    expect($bySize['M'])->toBe(2)
        ->and($bySize['L'])->toBe(1);
});

test('zapytanie listingu korzysta z indeksu listing_idx, nie COLLSCAN', function () {
    Product::raw(fn ($c) => $c->createIndex(
        ['active' => 1, 'category_path' => 1, 'avg_rating' => -1,
            'price' => 1],
        ['name' => 'listing_idx']
    ));

    Product::factory()->count(20)->create([
        'active' => true,
        'category_path' => ['Elektronika', 'Telefony'],
        'price' => 100,
    ]);
    Product::factory()->count(200)->create([
        'active' => true,
        'category_path' => ['Elektronika', 'Laptopy'],
    ]);

    $explain = DB::connection('mongodb')->getDatabase()->command([
        'explain' => ['find' => 'products', 'filter' => [
            'active' => true,
            'category_path' => 'Telefony',
            'price' => ['$lte' => new Decimal128('200')],
        ]],
        'verbosity' => 'executionStats',
    ])->toArray()[0];

    $plan = $explain['queryPlanner']['winningPlan'];
    $stats = $explain['executionStats'];

    expect($plan['inputStage']['indexName'])->toBe('listing_idx');

    expect($stats['nReturned'])->toBe(20)
        ->and($stats['totalDocsExamined'])->toBe(20);
});

test('byAttribute liczy dynamiczne atrybuty, a filtr po atrybucie zawęża listing', function () {
    Product::factory()->create(['active' => true, 'attributes' => ['material' => 'skóra', 'kraj' => 'IT']]);
    Product::factory()->create(['active' => true, 'attributes' => ['material' => 'skóra', 'kraj' => 'PL']]);
    Product::factory()->create(['active' => true, 'attributes' => ['material' => 'bawełna', 'kraj' => 'PL']]);

    $result = (new FacetedProductSearch)->handle([]);

    $byAttribute = collect($result['byAttribute'])->mapWithKeys(
        fn (array $b): array => [$b['_id']['key'].':'.$b['_id']['value'] => $b['count']]
    );
    expect($byAttribute['material:skóra'])->toBe(2)
        ->and($byAttribute['material:bawełna'])->toBe(1)
        ->and($byAttribute['kraj:PL'])->toBe(2)
        ->and($byAttribute['kraj:IT'])->toBe(1);

    $filtered = (new FacetedProductSearch)->handle(['attributes' => ['material' => 'skóra']]);
    expect($filtered['meta'][0]['total'])->toBe(2);
});

test('filtr po atrybucie korzysta z wildcard index attributes_wildcard_idx, nie COLLSCAN', function () {
    Product::raw(fn ($c) => $c->createIndex(
        ['attributes.$**' => 1],
        ['name' => 'attributes_wildcard_idx']
    ));

    Product::factory()->count(20)->create(['attributes' => ['material' => 'skóra', 'kraj' => 'IT']]);
    Product::factory()->count(200)->create(['attributes' => ['material' => 'bawełna', 'kraj' => 'PL']]);

    $explain = DB::connection('mongodb')->getDatabase()->command([
        'explain' => ['find' => 'products', 'filter' => ['attributes.material' => 'skóra']],
        'verbosity' => 'executionStats',
    ])->toArray()[0];

    $plan = $explain['queryPlanner']['winningPlan'];
    $stats = $explain['executionStats'];

    expect($plan['inputStage']['indexName'])->toBe('attributes_wildcard_idx');

    expect($stats['nReturned'])->toBe(20)
        ->and($stats['totalDocsExamined'])->toBe(20);
});
