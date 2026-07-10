<?php

namespace App\Actions\Products;

use App\Models\Review;
use MongoDB\BSON\ObjectId;

class MostReviewed
{
    private const LIMIT = 10;

    private const MAX_LIMIT = 100;

    public function handle(?string $productId = null, int $limit = self::LIMIT): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $pipeline = [
            ...($productId !== null ? [['$match' => ['product_id' => new ObjectId($productId)]]] : []),
            ['$group' => [
                '_id' => '$product_id',
                'reviews_count' => ['$sum' => 1],
                'avg_rating' => ['$avg' => '$rating'],
            ]],
            ['$sort' => ['reviews_count' => -1]],
            ['$limit' => $limit],
        ];

        $result = Review::raw(fn ($collection) => $collection->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray());

        return $result;
    }
}
