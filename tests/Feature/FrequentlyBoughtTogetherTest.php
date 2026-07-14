<?php

use App\Actions\Products\FrequentlyBoughtTogether;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Product::truncate();
    Order::truncate();
});

function order(Product ...$products): void
{
    $items = array_map(fn (Product $p): array => [
        'product_id' => new ObjectId($p->id),
        'name_snapshot' => $p->name,
        'price_snapshot' => new Decimal128('10.00'),
        'vendor_id' => new ObjectId,
        'quantity' => 1,
    ], $products);

    Order::create([
        'user_id' => 1,
        'user_snapshot' => ['name' => 'Tester', 'email' => 'tester@example.com'],
        'items' => $items,
        'total' => new Decimal128(number_format(count($items) * 10, 2, '.', '')),
        'status' => OrderStatus::Delivered->value,
    ]);
}

test('liczy produkty najczęściej kupowane razem z danym produktem', function () {
    $a = Product::factory()->create(['name' => 'AAA']);
    $b = Product::factory()->create(['name' => 'BBB']);
    $c = Product::factory()->create(['name' => 'CCC']);
    $d = Product::factory()->create(['name' => 'DDD']);

    order($a, $b, $c);
    order($a, $b);
    order($a, $b);
    order($a, $c);
    order($b, $d);

    $result = (new FrequentlyBoughtTogether)->handle($a);

    expect($result)->toHaveCount(2);

    $byName = collect($result)->pluck('count', 'name');
    expect($byName['BBB'])->toBe(3)
        ->and($byName['CCC'])->toBe(2);

    expect($result[0]['name'])->toBe('BBB');
});

test('match po items.product_id korzysta z indeksu order_items_product_idx (multikey), nie COLLSCAN', function () {
    Order::raw(fn ($c) => $c->createIndex(
        ['items.product_id' => 1],
        ['name' => 'order_items_product_idx']
    ));

    $target = Product::factory()->create(['name' => 'Target']);
    $noise = Product::factory()->create(['name' => 'Noise']);

    for ($i = 0; $i < 20; $i++) {
        order($target, $noise);
    }
    for ($i = 0; $i < 200; $i++) {
        order($noise);
    }

    $explain = DB::connection('mongodb')->getDatabase()->command([
        'explain' => ['find' => 'orders', 'filter' => [
            'items.product_id' => new ObjectId($target->id),
        ]],
        'verbosity' => 'executionStats',
    ])->toArray()[0];

    $plan = $explain['queryPlanner']['winningPlan'];
    $stats = $explain['executionStats'];

    expect($plan['inputStage']['indexName'])->toBe('order_items_product_idx')
        ->and($plan['inputStage']['isMultiKey'])->toBeTrue();

    expect($stats['nReturned'])->toBe(20)
        ->and($stats['totalDocsExamined'])->toBe(20);
});
