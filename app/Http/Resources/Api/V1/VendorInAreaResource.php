<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{_id: mixed, name: string, location: array{coordinates: array<int, float>}} $resource
 */
class VendorInAreaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vendor_id' => (string) $this->resource['_id'],
            'name' => $this->resource['name'],
            'location' => $this->resource['location']['coordinates'],
        ];
    }
}
