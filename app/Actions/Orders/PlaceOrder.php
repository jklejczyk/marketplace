<?php

namespace App\Actions\Orders;

use App\DataTransferObjects\RequestedItemData;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

class PlaceOrder
{
    private const MAX_ATTEMPTS = 3;

    /**
     * @param  list<RequestedItemData>  $requestedItems
     */
    public function handle(User $buyer, array $requestedItems): Order
    {
        return DB::connection('mongodb')->transaction(function () use ($buyer, $requestedItems) {
            $items = [];

            foreach ($requestedItems as $requestedItem) {
                $product = Product::findOrFail($requestedItem->productId);

                $variant = collect($product->variants)->where('sku', $requestedItem->sku)->first();
                if ($variant === null) {
                    throw new InsufficientStockException("Wariant {$requestedItem->sku} nie istnieje.");
                }

                $affected = Product::where('_id', new ObjectId($requestedItem->productId))
                    ->where('variants', 'elemMatch', [
                        'sku' => $requestedItem->sku,
                        'stock' => ['$gte' => $requestedItem->quantity],
                    ])
                    ->update(['$inc' => [
                        'variants.$.stock' => -$requestedItem->quantity,
                        'total_stock' => -$requestedItem->quantity,
                    ]]);

                if ($affected === 0) {
                    throw new InsufficientStockException("Za mało sztuk wariantu {$requestedItem->sku}.");
                }

                $items[] = [
                    'product_id' => new ObjectId($requestedItem->productId),
                    'name_snapshot' => $product->name,
                    'price_snapshot' => new Decimal128((string) $variant['price']),
                    'variant_snapshot' => [
                        'sku' => $variant['sku'],
                        'size' => $variant['size'],
                        'color' => $variant['color'],
                    ],
                    'vendor_id' => new ObjectId((string) $product->vendor_id),
                    'vendor_name' => $product->vendor_name,
                    'quantity' => $requestedItem->quantity,
                ];
            }

            $total = array_reduce($items, fn (string $carry, array $item): string => bcadd(
                $carry,
                bcmul((string) $item['price_snapshot'], (string)
                $item['quantity'], 2),
                2
            ), '0');

            return Order::create([
                'user_id' => $buyer->id,
                'user_snapshot' => ['name' => $buyer->name, 'email' => $buyer->email],
                'items' => $items,
                'total' => new Decimal128($total),
                'status' => OrderStatus::Pending->value,
            ]);
        }, attempts: self::MAX_ATTEMPTS);

    }
}
