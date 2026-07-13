<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{_id: mixed, name: string, price: mixed, score: float} $resource
 */
class ProductSearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource['_id'],
            'name' => $this->resource['name'],
            'price' => (string) $this->resource['price'],
            'score' => $this->resource['score'],
        ];
    }
}
