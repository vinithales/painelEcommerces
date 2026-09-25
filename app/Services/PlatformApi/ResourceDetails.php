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
        $images = self::productImages($r, $m, $url);
        if ($m && ! $images && ! empty($attrs['image']) && $attrs['image'] !== 'no_selection') {
            $images = [[
                'id' => null,
                'src' => self::safeUrl(rtrim($url, '/').'/media/catalog/product/'.ltrim($attrs['image'], '/')),
                'alt' => $r['name'] ?? '',
                'name' => basename((string) $attrs['image']),
                'position' => 1,
                'disabled' => false,
                'types' => ['image'],
                'media_type' => 'image',
                'file' => $attrs['image'],
                'date_created' => null,
            ]];
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
            'gallery' => $images,
        ];
    }

    private static function productImages(array $product, bool $magento, string $storeUrl): array
    {
        if ($magento) {
            return array_values(array_map(function (array $image, int $index) use ($storeUrl): array {
                $file = (string) ($image['file'] ?? '');

                return [
                    'id' => isset($image['id']) ? (int) $image['id'] : null,
                    'src' => self::safeUrl($file !== '' ? rtrim($storeUrl, '/').'/media/catalog/product/'.ltrim($file, '/') : null),
                    'alt' => (string) ($image['label'] ?? ''),
                    'name' => basename($file),
                    'position' => (int) ($image['position'] ?? $index + 1),
                    'disabled' => (bool) ($image['disabled'] ?? false),
                    'types' => array_values($image['types'] ?? []),
                    'media_type' => (string) ($image['media_type'] ?? 'image'),
                    'file' => $file,
                    'date_created' => null,
                ];
            }, $product['media_gallery_entries'] ?? [], array_keys($product['media_gallery_entries'] ?? [])));
        }

        return array_values(array_map(fn (array $image, int $index): array => [
            'id' => isset($image['id']) ? (int) $image['id'] : null,
            'src' => self::safeUrl($image['src'] ?? null),
            'alt' => (string) ($image['alt'] ?? ''),
            'name' => (string) ($image['name'] ?? $image['title'] ?? ''),
            'position' => $index,
            'disabled' => false,
            'types' => $index === 0 ? ['featured'] : [],
            'media_type' => 'image',
            'file' => null,
            'date_created' => $image['date_created'] ?? null,
        ], $product['images'] ?? [], array_keys($product['images'] ?? [])));
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
