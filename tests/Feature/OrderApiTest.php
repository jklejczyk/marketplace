<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Product::truncate();
    Order::truncate();
    $this->buyer = User::factory()->create();
});

function stockedProduct(string $sku, int $stock, string $price = '100.00'): Product
{
    return Product::factory()->create([
        'vendor_id' => (string) new ObjectId,
        'vendor_name' => 'Acme',
        'active' => true,
        'variants' => [[
            'sku' => $sku,
            'size' => 'M',
            'color' => 'red',
            'stock' => $stock,
            'price' => new Decimal128($price),
        ]],
        'total_stock' => $stock,
    ]);
}

test('POST /api/v1/orders wymaga uwierzytelnienia', function () {
    $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => 'x', 'sku' => 'y', 'quantity' => 1]],
    ])->assertUnauthorized();
});

test('POST /api/v1/orders wymaga items', function () {
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

test('POST /api/v1/orders odrzuca pustą listę items', function () {
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', ['items' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

test('POST /api/v1/orders odrzuca quantity mniejsze niż 1', function () {
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => 'x', 'sku' => 'y', 'quantity' => 0]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.quantity');
});

test('POST /api/v1/orders wymaga product_id i sku w każdej pozycji', function () {
    Sanctum::actingAs($this->buyer);

    $this->postJson('/api/v1/orders', [
        'items' => [['quantity' => 1]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.product_id', 'items.0.sku']);
});

test('POST /api/v1/orders składa zamówienie i dekrementuje stock (201)', function () {
    Sanctum::actingAs($this->buyer);
    $product = stockedProduct('ABC-M-RED', stock: 5, price: '100.00');

    $response = $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'sku' => 'ABC-M-RED', 'quantity' => 2]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.total', '200.00')
        ->assertJsonPath('data.items.0.quantity', 2);

    $product->refresh();
    $variant = collect($product->variants)->firstWhere('sku', 'ABC-M-RED');

    expect((int) $variant['stock'])->toBe(3)
        ->and($product->total_stock)->toBe(3)
        ->and(Order::count())->toBe(1);
});

test('POST /api/v1/orders zwraca 409 gdy za mało stocku i nie tyka bazy', function () {
    Sanctum::actingAs($this->buyer);
    $product = stockedProduct('ABC-M-RED', stock: 1);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'sku' => 'ABC-M-RED', 'quantity' => 5]],
    ]);

    $response->assertConflict();

    $product->refresh();
    $variant = collect($product->variants)->firstWhere('sku', 'ABC-M-RED');

    expect((int) $variant['stock'])->toBe(1)
        ->and(Order::count())->toBe(0);
});

test('POST /api/v1/orders rollbackuje udany decrement gdy druga pozycja nie ma stocku', function () {
    Sanctum::actingAs($this->buyer);
    $productA = stockedProduct('A-SKU', stock: 5);
    $productB = stockedProduct('B-SKU', stock: 1);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            ['product_id' => $productA->id, 'sku' => 'A-SKU', 'quantity' => 2],
            ['product_id' => $productB->id, 'sku' => 'B-SKU', 'quantity' => 5],
        ],
    ]);

    $response->assertConflict();

    $productA->refresh();
    $variantA = collect($productA->variants)->firstWhere('sku', 'A-SKU');

    expect((int) $variantA['stock'])->toBe(5)
        ->and(Order::count())->toBe(0);
});
