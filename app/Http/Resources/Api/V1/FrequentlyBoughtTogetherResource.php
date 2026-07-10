<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{_id: mixed, name: string, count: int} $resource
 */
class FrequentlyBoughtTogetherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => (string) $this->resource['_id'],
            'name' => $this->resource['name'],
            'count' => $this->resource['count'],
        ];
    }
}
