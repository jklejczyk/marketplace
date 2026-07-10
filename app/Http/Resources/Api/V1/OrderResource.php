<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'id' => (string) $order->id,
            'user_id' => $order->user_id,
            'items' => array_map(fn (array $item): array => [
                'product_id' => (string) $item['product_id'],
                'name' => $item['name_snapshot'] ?? null,
                'price' => isset($item['price_snapshot']) ? (string) $item['price_snapshot'] : null,
                'variant' => $item['variant_snapshot'] ?? null,
                'vendor_name' => $item['vendor_name'] ?? null,
                'quantity' => $item['quantity'],
            ], $order->items ?? []),
            'total' => isset($order->total) ? (string) $order->total : null,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
