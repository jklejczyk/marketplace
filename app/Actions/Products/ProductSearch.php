<?php

namespace App\Actions\Products;

use App\Models\Product;

class ProductSearch
{
    private const INDEX = 'products_search';

    private const LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function handle(string $query, int $limit = self::LIMIT, bool $activeOnly = false): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $compound = [
            'should' => [
                ['text' => [
                    'query' => $query,
                    'path' => 'name',
                    'score' => ['boost' => ['value' => 3]],
                ]],
                ['text' => [
                    'query' => $query,
                    'path' => 'description',
                    'score' => ['boost' => ['value' => 1]],
                ]],
                ['text' => [
                    'query' => $query,
                    'path' => ['value' => 'name', 'multi' => 'standard'],
                    'fuzzy' => ['maxEdits' => 1],
                    'score' => ['boost' => ['value' => 2]],
                ]],
            ],
            'minimumShouldMatch' => 1,
        ];

        if ($activeOnly) {
            $compound['filter'] = [
                ['equals' => ['path' => 'active', 'value' => true]],
            ];
        }

        $pipeline = [
            ['$search' => [
                'index' => self::INDEX,
                'compound' => $compound,
            ]],
            ['$limit' => $limit],
            ['$project' => [
                '_id' => 1,
                'name' => 1,
                'price' => 1,
                'score' => ['$meta' => 'searchScore'],
            ]],
        ];

        return Product::raw(fn ($collection) => $collection->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray());
    }
}
