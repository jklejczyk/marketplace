<?php

namespace App\DataTransferObjects;

final readonly class RequestedItemData
{
    public function __construct(
        public string $productId,
        public string $sku,
        public int $quantity,
    ) {}

    public static function fromArray(array $item): self
    {
        return new self(
            productId: $item['product_id'],
            sku: $item['sku'],
            quantity: (int) $item['quantity'],
        );
    }
}
