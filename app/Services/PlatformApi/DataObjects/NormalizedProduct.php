<?php

namespace App\Services\PlatformApi\DataObjects;

class NormalizedProduct
{
    public function __construct(
        public readonly string $id,
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $price,
        public readonly ?float $stockQuantity,
        public readonly ?string $status,
        public readonly ?string $createdAt,
        public readonly array $raw,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            sku: $data['sku'] ?? '',
            name: $data['name'] ?? '',
            price: isset($data['price']) ? (string) $data['price'] : null,
            stockQuantity: isset($data['stock_quantity']) ? (float) $data['stock_quantity'] : null,
            status: $data['status'] ?? null,
            createdAt: $data['created_at'] ?? null,
            raw: $data['raw'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'stock_quantity' => $this->stockQuantity,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'raw' => $this->raw,
        ];
    }
}
