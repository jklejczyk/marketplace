<?php

use App\Actions\Products\TopVendorsByRevenue;
use App\Models\Order;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Order::truncate();
});

function orderWithItems(array $items): void
{
    Order::create([
        'user_id' => 1,
        'user_snapshot' => ['name' => 'Tester', 'email' => 'tester@example.com'],
        'items' => array_map(fn (array $item): array => [
            'product_id' => new ObjectId,
            'name_snapshot' => 'Produkt',
            'price_snapshot' => new Decimal128($item[2]),
            'vendor_id' => $item[0],
            'vendor_name' => $item[1],
            'quantity' => $item[3],
        ], $items),
    ]);
}

test('sumuje przychód per vendor przez wszystkie zamówienia i sortuje malejąco', function () {
    $acme = new ObjectId;
    $globex = new ObjectId;
    $initech = new ObjectId;

    orderWithItems([
        [$acme, 'Acme', '100.00', 2],
        [$globex, 'Globex', '30.00', 1],
    ]);
    orderWithItems([
        [$acme, 'Acme', '50.00', 1],
        [$initech, 'Initech', '10.00', 1],
    ]);
    orderWithItems([
        [$globex, 'Globex', '30.00', 2],
    ]);

    $result = (new TopVendorsByRevenue)->handle();

    expect($result)->toHaveCount(3);

    $revenueByVendor = collect($result)->mapWithKeys(fn (array $row): array => [
        $row['vendor_name'] => (string) $row['revenue'],
    ]);

    expect($revenueByVendor['Acme'])->toBe('250.00')
        ->and($revenueByVendor['Globex'])->toBe('90.00')
        ->and($revenueByVendor['Initech'])->toBe('10.00');

    expect($result[0]['vendor_name'])->toBe('Acme');
});

test('revenue wraca jako Decimal128 (liczony po stronie Mongo), nie float', function () {
    orderWithItems([
        [new ObjectId, 'Acme', '19.99', 3],
    ]);

    $result = (new TopVendorsByRevenue)->handle();

    expect($result[0]['revenue'])->toBeInstanceOf(Decimal128::class);
});
