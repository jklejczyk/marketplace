<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{_id: mixed, vendor_name: string, revenue: mixed} $resource
 */
class TopVendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vendor_id' => (string) $this->resource['_id'],
            'vendor_name' => $this->resource['vendor_name'],
            'revenue' => (string) $this->resource['revenue'],
        ];
    }
}
