<?php

namespace App\Services\PlatformApi;

class ResourceDetails
{
    public static function product(array $r, string $platform, string $url): array
    {
        $m = $platform === 'magento2';
        $attrs = [];
        foreach ($r['custom_attributes'] ?? [] as $a) {
            $attrs[$a['attribute_code']] = $a['value'] ?? null;
        }
        $images = $m ? array_map(fn ($i) => ['src' => rtrim($url, '/').'/media/catalog/product/'.ltrim($i['file'] ?? '', '/'), 'alt' => $i['label'] ?? ''], $r['media_gallery_entries'] ?? []) : ($r['images'] ?? []);
        if ($m && ! $images && ! empty($attrs['image']) && $attrs['image'] !== 'no_selection') {
            $images = [['src' => rtrim($url, '/').'/media/catalog/product/'.ltrim($attrs['image'], '/'), 'alt' => $r['name'] ?? '']];
        }
        $link = $m ? (isset($r['id']) ? rtrim($url, '/').'/catalog/product/view/id/'.rawurlencode((string) $r['id']) : null) : ($r['permalink'] ?? null);

        return [
            'key' => (string) ($m ? ($r['sku'] ?? '') : ($r['id'] ?? '')),
            'image' => $images[0]['src'] ?? null, 'url' => self::safeUrl($link), 'weight' => $r['weight'] ?? null,
            'type' => $r['type_id'] ?? $r['type'] ?? null, 'visibility' => $r['visibility'] ?? $r['catalog_visibility'] ?? null,
            'description' => $r['description'] ?? $attrs['description'] ?? null, 'short_description' => $r['short_description'] ?? $attrs['short_description'] ?? null,
            'regular_price' => $m ? ($r['price'] ?? null) : ($r['regular_price'] ?? null), 'sale_price' => $r['sale_price'] ?? $attrs['special_price'] ?? null,
            'currency' => $r['currency'] ?? null, 'manage_stock' => $m ? ($r['extension_attributes']['stock_item']['manage_stock'] ?? null) : ($r['manage_stock'] ?? null),
            'stock_status' => $m ? ($r['extension_attributes']['stock_item']['is_in_stock'] ?? null) : ($r['stock_status'] ?? null),
            'dimensions' => $r['dimensions'] ?? null, 'categories' => $r['categories'] ?? $attrs['category_ids'] ?? [],
            'attributes' => $r['attributes'] ?? $attrs, 'variations' => $r['variations'] ?? $r['extension_attributes']['configurable_product_links'] ?? [],
            'gallery' => array_map(fn ($i) => ['src' => self::safeUrl($i['src'] ?? null), 'alt' => $i['alt'] ?? ''], $images),
        ];
    }

    public static function order(array $r, string $platform): array
    {
        $m = $platform === 'magento2';

        return ['number' => (string) ($r['increment_id'] ?? $r['number'] ?? $r['id'] ?? ''), 'state' => $r['state'] ?? null,
            'currency' => $r['order_currency_code'] ?? $r['currency'] ?? null, 'phone' => $r['billing_address']['telephone'] ?? $r['billing']['phone'] ?? null,
            'items' => $r['items'] ?? $r['line_items'] ?? [], 'subtotal' => $r['subtotal'] ?? array_sum(array_column($r['line_items'] ?? [], 'subtotal')),
            'discount' => $r['discount_amount'] ?? $r['discount_total'] ?? null, 'shipping_total' => $r['shipping_amount'] ?? $r['shipping_total'] ?? null, 'tax' => $r['tax_amount'] ?? $r['total_tax'] ?? null,
            'payment_method' => $r['payment']['method'] ?? $r['payment_method_title'] ?? $r['payment_method'] ?? null,
            'transaction_id' => $r['payment']['last_trans_id'] ?? $r['transaction_id'] ?? null,
            'payment_status' => $r['date_paid'] ?? ($r['total_paid'] ?? null),
            'shipping_method' => $r['shipping_description'] ?? ($r['shipping_lines'][0]['method_title'] ?? null),
            'shipping_address' => $r['extension_attributes']['shipping_assignments'][0]['shipping']['address'] ?? $r['shipping'] ?? [],
            'tracking' => $r['extension_attributes']['tracking'] ?? null,
        ];
    }

    public static function safeUrl(?string $url): ?string
    {
        return $url && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $url : null;
    }
}
