<?php

namespace App\Actions\Products;

use App\Models\Order;
use App\Models\Product;
use MongoDB\BSON\ObjectId;

class FrequentlyBoughtTogether
{
    private const LIMIT = 10;

    private const MAX_LIMIT = 100;

    public function handle(Product $product, int $limit = self::LIMIT): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $pipeline = [
            ['$match' => ['items.product_id' => new ObjectId($product->id)]],
            ['$unwind' => '$items'],
            ['$match' => ['items.product_id' => ['$ne' => new ObjectId($product->id)]]],
            ['$group' => [
                '_id' => '$items.product_id',
                'count' => ['$sum' => 1],
                'name' => ['$first' => '$items.name_snapshot'],
            ],
            ],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit],
        ];

        $result = Order::raw(fn ($collection) => $collection->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray());

        return $result;
    }
}
