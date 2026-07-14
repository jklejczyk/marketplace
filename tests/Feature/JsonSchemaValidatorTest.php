<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Exception\BulkWriteException;

function applyValidator(string $collection): void
{
    $db = DB::connection('mongodb')->getDatabase();

    try {
        $db->createCollection($collection);
    } catch (Throwable) {
    }

    $migration = glob(database_path("migrations/*_add_jsonschema_validator_to_{$collection}.php"))[0];
    (include $migration)->up();
}

/**
 * @return array<string, mixed>
 */
function validOrder(): array
{
    return [
        'user_id' => 1,
        'user_snapshot' => ['name' => 'Tester', 'email' => 'tester@example.com'],
        'items' => [[
            'product_id' => new ObjectId,
            'name_snapshot' => 'Produkt',
            'price_snapshot' => new Decimal128('10.00'),
            'vendor_id' => new ObjectId,
            'quantity' => 1,
        ]],
        'total' => new Decimal128('10.00'),
        'status' => OrderStatus::Delivered->value,
    ];
}

/**
 * @return array<string, mixed>
 */
function validProduct(): array
{
    return [
        'name' => 'Testowy Produkt',
        'slug' => 'testowy-produkt-1',
        'price' => new Decimal128('99.99'),
        'variants' => [[
            'sku' => 'SKU-1',
            'stock' => 5,
            'price' => new Decimal128('99.99'),
        ]],
        'active' => true,
    ];
}

beforeEach(function () {
    applyValidator('orders');
    applyValidator('products');
    Order::truncate();
    Product::truncate();
});

test('orders: validator odrzuca niepoprawny dokument', function (Closure $mutate) {
    $doc = $mutate(validOrder());

    expect(fn () => Order::raw(fn ($collection) => $collection->insertOne($doc)))
        ->toThrow(BulkWriteException::class);
})->with([
    'brak pola status' => [fn (array $d): array => collect($d)->except('status')->all()],
    'status spoza enuma' => [fn (array $d): array => [...$d, 'status' => 'banana']],
    'total nie-Decimal128' => [fn (array $d): array => [...$d, 'total' => '100']],
    'items puste (minItems 1)' => [fn (array $d): array => [...$d, 'items' => []]],
    'quantity < 1' => [function (array $d): array {
        $d['items'][0]['quantity'] = 0;

        return $d;
    }],
    'pozycja bez vendor_id' => [function (array $d): array {
        unset($d['items'][0]['vendor_id']);

        return $d;
    }],
]);

test('orders: validator przepuszcza poprawne zamówienie ze statusem z enuma', function () {
    Order::raw(fn ($collection) => $collection->insertOne([...validOrder(), 'status' => OrderStatus::Paid->value]));

    expect(Order::where('status', OrderStatus::Paid->value)->count())->toBe(1);
});

test('products: validator odrzuca niepoprawny dokument', function (Closure $mutate) {
    $doc = $mutate(validProduct());

    expect(fn () => Product::raw(fn ($collection) => $collection->insertOne($doc)))
        ->toThrow(BulkWriteException::class);
})->with([
    'nazwa < 3 znaki' => [fn (array $d): array => [...$d, 'name' => 'AB']],
    'cena nie-Decimal128' => [fn (array $d): array => [...$d, 'price' => 'za darmo']],
    'cena = 0 (nie > 0)' => [fn (array $d): array => [...$d, 'price' => new Decimal128('0')]],
    'variants puste (minItems 1)' => [fn (array $d): array => [...$d, 'variants' => []]],
    'wariant bez sku' => [function (array $d): array {
        unset($d['variants'][0]['sku']);

        return $d;
    }],
    'brak wymaganego active' => [fn (array $d): array => collect($d)->except('active')->all()],
]);

test('products: validator przepuszcza produkt z nullowym vendorem (nullable FK)', function () {
    Product::raw(fn ($collection) => $collection->insertOne([
        ...validProduct(),
        'vendor_id' => null,
        'vendor_name' => null,
        'category_id' => null,
    ]));

    expect(Product::count())->toBe(1);
});
