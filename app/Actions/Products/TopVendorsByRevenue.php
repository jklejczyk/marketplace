<?php

namespace App\Actions\Products;

use App\Models\Order;

class TopVendorsByRevenue
{
    private const LIMIT = 10;

    private const MAX_LIMIT = 100;

    public function handle(int $limit = self::LIMIT): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $pipeline = [
            ['$unwind' => '$items'],
            ['$group' => [
                '_id' => '$items.vendor_id',
                'revenue' => ['$sum' => ['$multiply' => ['$items.price_snapshot', '$items.quantity']]],
                'vendor_name' => ['$first' => '$items.vendor_name'],
            ]],
            ['$sort' => ['revenue' => -1]],
            ['$limit' => $limit],
        ];

        $result = Order::raw(fn ($collection) => $collection->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray());

        return $result;
    }
}
