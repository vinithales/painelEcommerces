<?php

namespace App\Services\PlatformApi\Contracts;

interface PlatformApiAdapter
{
    /**
     * List products with optional filters.
     *
     * Accepted filter keys: sku, sku_prefix, ids (array), status, date_from, date_to.
     *
     * @return array{data: array<int, array<string, mixed>>, raw: array<string, mixed>, total: int}
     */
    public function listProducts(array $filters, int $page, int $perPage): array;

    /**
     * Fetch a single product by its platform ID.
     *
     * @return array{data: array<string, mixed>, raw: array<string, mixed>}
     */
    public function getProduct(string $id): array;

    /**
     * Update a product by its platform ID.
     *
     * @return array{data: array<string, mixed>, raw: array<string, mixed>}
     */
    public function updateProduct(string $id, array $attributes): array;

    /**
     * List orders with optional filters.
     *
     * Accepted filter keys: status, date_from, date_to, customer_email.
     *
     * @return array{data: array<int, array<string, mixed>>, raw: array<string, mixed>, total: int}
     */
    public function listOrders(array $filters, int $page, int $perPage): array;

    /**
     * Fetch a single order by its platform ID.
     *
     * @return array{data: array<string, mixed>, raw: array<string, mixed>}
     */
    public function getOrder(string $id): array;

    /**
     * Update an order by its platform ID.
     *
     * @return array{data: array<string, mixed>, raw: array<string, mixed>}
     */
    public function updateOrder(string $id, array $attributes): array;
}
