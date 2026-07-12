<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{_id: mixed, name: string, distance_m: float} $resource
 */
class VendorNearbyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vendor_id' => (string) $this->resource['_id'],
            'name' => $this->resource['name'],
            'distance_m' => round((float) $this->resource['distance_m'], 1),
        ];
    }
}
