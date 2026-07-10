<?php

namespace App\Actions\Products;

use App\Models\Review;
use MongoDB\BSON\ObjectId;

class MostReviewed
{
    private const LIMIT = 10;

    public function handle(?string $productId = null): array
    {
        $pipeline = [
            ...($productId !== null ? [['$match' => ['product_id' => new ObjectId($productId)]]] : []),
            ['$group' => [
                '_id' => '$product_id',
                'reviews_count' => ['$sum' => 1],
                'avg_rating' => ['$avg' => '$rating'],
            ]],
            ['$sort' => ['reviews_count' => -1]],
            ['$limit' => self::LIMIT],
        ];

        $result = Review::raw(fn ($collection) => $collection->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray());

        return $result;
    }
}
