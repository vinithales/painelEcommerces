<?php

namespace App\Services\IntegrationCheckers;

use App\Models\Store;
use App\Services\IntegrationCheckers\Checks\IndexCheck;
use App\Services\IntegrationCheckers\Checks\RobotsTxtCheck;
use App\Services\IntegrationCheckers\Checks\StoreCheck;
use Illuminate\Contracts\Container\Container;
use Throwable;

class RunStoreChecks
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return array<int, array{check_key: string, status: string, error: ?string}>
     */
    public function handle(Store $store): array
    {
        $results = [];

        foreach ($this->checks() as $checkClass) {
            /** @var StoreCheck $check */
            $check = $this->container->make($checkClass);

            if (! $check->supports($store)) {
                continue;
            }

            try {
                $result = $check->check($store);
            } catch (Throwable $exception) {
                report($exception);

                $result = [
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }

            $results[] = [
                'check_key' => $check->key(),
                'status' => $result['status'],
                'error' => $result['error'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * @return array<int, class-string<StoreCheck>>
     */
    private function checks(): array
    {
        return [
            RobotsTxtCheck::class,
            IndexCheck::class,
        ];
    }
}