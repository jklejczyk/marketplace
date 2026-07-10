<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{_id: mixed, reviews_count: int, avg_rating: float} $resource
 */
class MostReviewedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => (string) $this->resource['_id'],
            'reviews_count' => $this->resource['reviews_count'],
            'avg_rating' => round((float) $this->resource['avg_rating'], 2),
        ];
    }
}
