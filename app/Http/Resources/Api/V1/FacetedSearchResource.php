<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owija surowy wynik $facet z FacetedProductSearch (tablica, nie model)
 * i czyści skalary BSON (ObjectId, Decimal128) do stringów.
 *
 * @property array<string, mixed> $resource
 */
class FacetedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'results' => array_map(fn (array $product): array => [
                'id' => (string) $product['_id'],
                'name' => $product['name'] ?? null,
                'price' => isset($product['price']) ? (string) $product['price'] : null,
                'vendor_name' => $product['vendor_name'] ?? null,
                'category_path' => $product['category_path'] ?? null,
            ], $this->resource['results'] ?? []),
            'facets' => [
                'byVendor' => array_map(fn (array $bucket): array => [
                    'vendor_name' => $bucket['_id'],
                    'count' => $bucket['count'],
                ], $this->resource['byVendor'] ?? []),
                'byPrice' => array_map(fn (array $bucket): array => [
                    // _id to granica Decimal128 albo string 'inne' (default bucketa)
                    'bucket' => is_object($bucket['_id']) ? (string) $bucket['_id'] : $bucket['_id'],
                    'count' => $bucket['count'],
                ], $this->resource['byPrice'] ?? []),
                'bySize' => array_map(fn (array $bucket): array => [
                    'size' => $bucket['_id'],
                    'count' => $bucket['count'],
                ], $this->resource['bySize'] ?? []),
            ],
            'meta' => [
                'total' => $this->resource['meta'][0]['total'] ?? 0,
            ],
        ];
    }
}
