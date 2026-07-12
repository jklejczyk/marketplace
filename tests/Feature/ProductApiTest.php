<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Product::truncate();
    Order::truncate();
    Review::truncate();
    User::factory()->create();
});

test('GET /api/v1/vendors/top serializuje revenue jako string', function () {
    Order::create([
        'user_id' => 1,
        'user_snapshot' => ['name' => 'T', 'email' => 't@e.com'],
        'items' => [[
            'product_id' => new ObjectId,
            'name_snapshot' => 'P',
            'price_snapshot' => new Decimal128('100.00'),
            'vendor_id' => new ObjectId,
            'vendor_name' => 'Acme',
            'quantity' => 2,
        ]],
    ]);

    $response = $this->getJson('/api/v1/vendors/top');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['vendor_id', 'vendor_name', 'revenue']]]);

    $row = $response->json('data.0');
    expect($row['vendor_id'])->toBeString()
        ->and($row['vendor_name'])->toBe('Acme')
        ->and($row['revenue'])->toBe('200.00');
});

test('GET /api/v1/products/most-reviewed serializuje product_id jako string', function () {
    $product = Product::factory()->create();
    Review::factory()->forProduct($product)->create(['rating' => 5]);
    Review::factory()->forProduct($product)->create(['rating' => 3]);

    $response = $this->getJson('/api/v1/products/most-reviewed');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['product_id', 'reviews_count', 'avg_rating']]]);

    $row = $response->json('data.0');
    expect($row['product_id'])->toBeString()
        ->and($row['reviews_count'])->toBe(2)
        ->and((float) $row['avg_rating'])->toBe(4.0);
});

test('GET /api/v1/products/{product}/frequently-bought-together rozwiązuje binding po _id', function () {
    $a = Product::factory()->create(['name' => 'A']);
    $b = Product::factory()->create(['name' => 'B']);

    $makeOrder = function (Product ...$products): void {
        Order::create([
            'user_id' => 1,
            'user_snapshot' => ['name' => 'T', 'email' => 't@e.com'],
            'items' => array_map(fn (Product $p): array => [
                'product_id' => new ObjectId($p->id),
                'name_snapshot' => $p->name,
                'quantity' => 1,
            ], $products),
        ]);
    };
    $makeOrder($a, $b);
    $makeOrder($a, $b);

    $response = $this->getJson("/api/v1/products/{$a->id}/frequently-bought-together");

    $response->assertOk()
        ->assertJsonStructure(['data' => [['product_id', 'name', 'count']]]);

    $row = $response->json('data.0');
    expect($row['product_id'])->toBeString()
        ->and($row['name'])->toBe('B')
        ->and($row['count'])->toBe(2);
});

test('GET /api/v1/products zwraca strukturę facets i czyści BSON', function () {
    Product::factory()->count(3)->create(['active' => true]);

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'results' => [['id', 'name', 'price', 'vendor_name', 'category_path']],
                'facets' => ['byVendor', 'byPrice', 'bySize'],
                'meta' => ['total'],
            ],
        ]);

    expect($response->json('data.results.0.id'))->toBeString()
        ->and($response->json('data.results.0.price'))->toBeString();
});

test('GET /api/v1/products odrzuca niedozwolony sort (422)', function () {
    $this->getJson('/api/v1/products?sort=nonsense')->assertUnprocessable();
});

test('GET /api/v1/products ogranicza liczbę wyników przez per_page', function () {
    Product::factory()->count(5)->create(['active' => true]);

    $response = $this->getJson('/api/v1/products?per_page=2');

    $response->assertOk();
    expect($response->json('data.results'))->toHaveCount(2);
});

test('GET /api/v1/products odrzuca per_page ponad limit (422)', function () {
    $this->getJson('/api/v1/products?per_page=999')->assertUnprocessable();
});

test('GET /api/v1/vendors/top respektuje parametr limit', function () {
    foreach (['A', 'B', 'C'] as $vendorName) {
        Order::create([
            'user_id' => 1,
            'user_snapshot' => ['name' => 'T', 'email' => 't@e.com'],
            'items' => [[
                'product_id' => new ObjectId,
                'name_snapshot' => 'P',
                'price_snapshot' => new Decimal128('10.00'),
                'vendor_id' => new ObjectId,
                'vendor_name' => $vendorName,
                'quantity' => 1,
            ]],
        ]);
    }

    $this->getJson('/api/v1/vendors/top?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/v1/vendors/top odrzuca limit ponad 100 (422)', function () {
    $this->getJson('/api/v1/vendors/top?limit=999')->assertUnprocessable();
});

test('GET /api/v1/products/most-reviewed respektuje parametr limit', function () {
    foreach (range(1, 3) as $i) {
        $product = Product::factory()->create();
        Review::factory()->forProduct($product)->create(['rating' => 5]);
    }

    $this->getJson('/api/v1/products/most-reviewed?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/v1/products/{product}/frequently-bought-together respektuje limit', function () {
    $target = Product::factory()->create();
    $others = Product::factory()->count(3)->create();

    foreach ($others as $other) {
        Order::create([
            'user_id' => 1,
            'user_snapshot' => ['name' => 'T', 'email' => 't@e.com'],
            'items' => [
                ['product_id' => new ObjectId($target->id), 'name_snapshot' => $target->name, 'quantity' => 1],
                ['product_id' => new ObjectId($other->id), 'name_snapshot' => $other->name, 'quantity' => 1],
            ],
        ]);
    }

    $this->getJson("/api/v1/products/{$target->id}/frequently-bought-together?limit=2")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('GET /api/v1/vendors/nearby zwraca vendorów w promieniu z dystansem, wyklucza dalekich', function () {
    Vendor::truncate();
    Vendor::raw(fn ($collection) => $collection->createIndex(['location' => '2dsphere']));

    Vendor::factory()->create([
        'name' => 'Blisko',
        'location' => ['type' => 'Point', 'coordinates' => [21.001, 52.231]],
    ]);
    Vendor::factory()->create([
        'name' => 'Kraków',
        'location' => ['type' => 'Point', 'coordinates' => [19.945, 50.065]],
    ]);

    $response = $this->getJson('/api/v1/vendors/nearby?lng=21.00&lat=52.23&radius=5000');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['vendor_id', 'name', 'distance_m']]])
        ->assertJsonCount(1, 'data');

    $row = $response->json('data.0');
    expect($row['vendor_id'])->toBeString()
        ->and($row['name'])->toBe('Blisko')
        ->and($row['distance_m'])->toBeLessThan(1000.0);
});

test('GET /api/v1/vendors/nearby wymaga lat i lng (422)', function () {
    $this->getJson('/api/v1/vendors/nearby')->assertUnprocessable();
});

test('POST /api/v1/vendors/in-area zwraca vendorów wewnątrz wielokąta', function () {
    Vendor::truncate();

    Vendor::factory()->create([
        'name' => 'W środku',
        'location' => ['type' => 'Point', 'coordinates' => [21.00, 52.23]],
    ]);
    Vendor::factory()->create([
        'name' => 'Kraków',
        'location' => ['type' => 'Point', 'coordinates' => [19.945, 50.065]],
    ]);

    $response = $this->postJson('/api/v1/vendors/in-area', [
        'ring' => [[20.9, 52.15], [21.1, 52.15], [21.1, 52.30], [20.9, 52.30]],
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['vendor_id', 'name', 'location']]])
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.name'))->toBe('W środku')
        ->and($response->json('data.0.vendor_id'))->toBeString();
});

test('POST /api/v1/vendors/in-area wymaga ring (422)', function () {
    $this->postJson('/api/v1/vendors/in-area', [])->assertUnprocessable();
});
