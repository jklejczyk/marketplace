<?php

use App\Models\Cart;

beforeEach(function () {
    Cart::truncate();

    try {
        Cart::raw(fn ($collection) => $collection->dropIndex('cart_ttl_idx'));
    } catch (Throwable) {
    }
});

test('migracja tworzy TTL index cart_ttl_idx na updated_at z expireAfterSeconds 604800', function () {
    $path = glob(base_path('database/migrations/*_create_carts_table.php'))[0];
    $migration = include $path;
    $migration->up();

    $indexes = collect(
        Cart::raw(fn ($collection) => iterator_to_array($collection->listIndexes()))
    );

    $ttl = $indexes->first(fn ($index) => $index->getName() === 'cart_ttl_idx');

    expect($ttl)->not->toBeNull()
        ->and($ttl->getKey())->toBe(['updated_at' => 1])
        ->and($ttl->isTtl())->toBeTrue()
        ->and($ttl['expireAfterSeconds'])->toBe(604800);
});
