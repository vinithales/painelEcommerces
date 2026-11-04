<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Store extends Model
{
    public function serverConnection()
    {
        return $this->hasOne(ServerConnection::class);
    }

    public function audits()
    {
        return $this->hasMany(AuditEvent::class);
    }

    use HasFactory;

    public const PLATFORM_WOOCOMMERCE = 'woocommerce';

    public const PLATFORM_MAGENTO2 = 'magento2';

    protected $fillable = [
        'name',
        'platform',
        'store_url',
        'panel_prefix',
        'payment_gateway',
        'shipping_gateway',
    ];

    public function wooCommerceIntegration(): HasOne
    {
        return $this->hasOne(WooCommerceIntegration::class);
    }

    public function magento2Integration(): HasOne
    {
        return $this->hasOne(Magento2Integration::class);
    }

    public function isWooCommerce(): bool
    {
        return $this->platform === self::PLATFORM_WOOCOMMERCE;
    }

    public function isMagento2(): bool
    {
        return $this->platform === self::PLATFORM_MAGENTO2;
    }

    public function usesAccessToken(): bool
    {
        return self::platformUsesAccessToken($this->platform);
    }

    public static function platformUsesAccessToken(string $platform): bool
    {
        return $platform === self::PLATFORM_MAGENTO2;
    }

    public function integration(): WooCommerceIntegration|Magento2Integration|null
    {
        return match ($this->platform) {
            self::PLATFORM_WOOCOMMERCE => $this->wooCommerceIntegration,
            self::PLATFORM_MAGENTO2 => $this->magento2Integration,
            default => null,
        };
    }

    public function integrationRelation(): HasOne
    {
        return match ($this->platform) {
            self::PLATFORM_WOOCOMMERCE => $this->wooCommerceIntegration(),
            self::PLATFORM_MAGENTO2 => $this->magento2Integration(),
            default => throw new InvalidArgumentException("Unsupported store platform [{$this->platform}]."),
        };
    }

    public function clearIntegrations(): void
    {
        $this->wooCommerceIntegration()->delete();
        $this->magento2Integration()->delete();
    }

    public function syncIntegration(array $integrationData): void
    {
        $this->integrationRelation()->create($integrationData);
    }

    public function replaceIntegration(array $integrationData): void
    {
        $this->clearIntegrations();
        $this->syncIntegration($integrationData);
    }

    public function integrationCredentials(): array
    {
        return $this->integration()?->credentials() ?? [];
    }

    public function checkAssignments(): HasMany
    {
        return $this->hasMany(CheckAssignment::class);
    }

    public function checkExecutions()
    {
        return $this->hasManyThrough(CheckExecution::class, CheckAssignment::class);
    }

}
