<?php

namespace App\Services\PlatformApi\DataObjects;

class NormalizedOrder
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $status,
        public readonly string $customer,
        public readonly ?string $customerEmail,
        public readonly ?string $total,
        public readonly ?string $createdAt,
        public readonly array $raw,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            status: $data['status'] ?? null,
            customer: $data['customer'] ?? '',
            customerEmail: $data['customer_email'] ?? null,
            total: isset($data['total']) ? (string) $data['total'] : null,
            createdAt: $data['created_at'] ?? null,
            raw: $data['raw'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'customer' => $this->customer,
            'customer_email' => $this->customerEmail,
            'total' => $this->total,
            'created_at' => $this->createdAt,
            'raw' => $this->raw,
        ];
    }
}
