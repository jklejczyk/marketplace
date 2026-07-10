<?php

namespace App\Actions\Products;

use App\Models\Product;
use MongoDB\BSON\Decimal128;

class FacetedProductSearch
{
    private const PER_PAGE = 20;

    /**
     * @param  array{category?: string, tags?: list<string>, priceMin?: float|int, priceMax?: float|int, sort?: string, page?: int}  $filters
     * @return array{results: list<array<string, mixed>>, byVendor: list<array<string, mixed>>, byPrice: list<array<string, mixed>>, bySize: list<array<string, mixed>>, meta: list<array{total: int}>}
     */
    public function handle(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));

        $pipeline = [
            ['$match' => $this->buildMatch($filters)],
            ['$facet' => [
                'results' => [
                    ['$sort' => $this->buildSort($filters)],
                    ['$skip' => ($page - 1) * self::PER_PAGE],
                    ['$limit' => self::PER_PAGE],
                    ['$project' => [
                        'name' => 1,
                        'price' => 1,
                        'vendor_name' => 1,
                        'category_path' => 1,
                    ]],
                ],
                'byVendor' => [
                    ['$sortByCount' => '$vendor_name'],
                ],
                'byPrice' => [
                    ['$bucket' => [
                        'groupBy' => '$price',
                        'boundaries' => [
                            new Decimal128('0'),
                            new Decimal128('50'),
                            new Decimal128('100'),
                            new Decimal128('200'),
                            new Decimal128('1000000'),
                        ],
                        'default' => 'inne',
                        'output' => ['count' => ['$sum' => 1]],
                    ]],
                ],
                'bySize' => [
                    ['$unwind' => '$variants'],
                    ['$sortByCount' => '$variants.size'],
                ],
                'meta' => [
                    ['$count' => 'total'],
                ],
            ]],
        ];

        $result = Product::raw(fn ($collection) => $collection->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray());

        return $result[0] ?? [];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildMatch(array $filters): array
    {
        $match = ['active' => true];

        if (! empty($filters['category'])) {
            $match['category_path'] = $filters['category'];
        }

        if (! empty($filters['tags'])) {
            $match['tags'] = ['$in' => (array) $filters['tags']];
        }

        $price = [];
        if (isset($filters['priceMin'])) {
            $price['$gte'] = new Decimal128((string) $filters['priceMin']);
        }
        if (isset($filters['priceMax'])) {
            $price['$lte'] = new Decimal128((string) $filters['priceMax']);
        }
        if ($price !== []) {
            $match['price'] = $price;
        }

        return $match;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function buildSort(array $filters): array
    {
        return match ($filters['sort'] ?? null) {
            'price_asc' => ['price' => 1],
            'price_desc' => ['price' => -1],
            'newest' => ['created_at' => -1],
            default => ['avg_rating' => -1],
        };
    }
}
