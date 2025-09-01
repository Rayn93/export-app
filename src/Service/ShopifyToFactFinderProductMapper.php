<?php
declare(strict_types=1);

namespace App\Service;

class ShopifyToFactFinderProductMapper
{
    public function map(array $shopifyProducts): array
    {
        $rows = [];

        foreach ($shopifyProducts as $product) {
            $masterId   = (string) $product['id'];
            $brand      = $product['vendor'] ?? '';
            $category   = $this->buildCategoryPath($product);
            $deeplink   = "/products/{$product['handle']}";
            $description = strip_tags($product['body_html'] ?? '');
            $imageUrl   = $product['images'][0]['src'] ?? '';

            $variants   = $product['variants'] ?? [];
            $hasMultipleVariants = count($variants) > 1;

            // --- rekord mastera ---
            $rows[] = [
                'ProductNumber' => $masterId,
                'Master'        => $masterId,
                'Name'          => $product['title'] ?? '',
                'Brand'         => $brand,
                'CategoryPath'  => $category,
                'Deeplink'      => $deeplink,
                'Description'   => $description,
                'ImageUrl'      => $imageUrl,
                'Price'         => $variants[0]['price'] ?? '',
                'FilterAttributes' => $this->buildMasterFilterAttributes($variants, $product['options'] ?? []),
            ];

            // --- tylko jeśli jest więcej niż 1 wariant ---
            if ($hasMultipleVariants) {
                foreach ($variants as $variant) {
                    $rows[] = [
                        'ProductNumber' => (string) ($variant['id'] ?? ''),
                        'Master'        => $masterId,
                        'Name'          => trim($product['title'] . ' ' . ($variant['title'] !== 'Default Title' ? $variant['title'] : '')),
                        'Brand'         => $brand,
                        'CategoryPath'  => $category,
                        'Deeplink'      => $deeplink,
                        'Description'   => $description,
                        'ImageUrl'      => $imageUrl,
                        'Price'         => $variant['price'] ?? '',
                        'FilterAttributes' => $this->buildVariantFilterAttributes($variant, $product['options'] ?? []),
                    ];
                }
            }
        }

        return $rows;
    }

    private function buildCategoryPath(array $product): string
    {
        // Shopify nie ma hierarchii kategorii natywnie – musisz wymyślić mapping
        // Na start: użyj `product_type` jako CategoryPath
        if (!empty($product['product_type'])) {
            return $product['product_type'];
        }

        return 'Uncategorized';
    }

    private function buildMasterFilterAttributes(array $variants, array $options): string
    {
        $filters = [];

        foreach ($options as $index => $option) {
            $name = strtolower($option['name']);
            $values = [];

            foreach ($variants as $variant) {
                $value = $variant['option' . ($index + 1)] ?? null;
                if ($value && !in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }

            foreach ($values as $value) {
                $filters[] = "{$name}={$value}";
            }
        }

        return '|' . implode('|', $filters) . '|';
    }


    private function buildVariantFilterAttributes(array $variant, array $options): string
    {
        $filters = [];

        foreach ($options as $index => $option) {
            $name = strtolower($option['name']);
            $value = $variant['option' . ($index + 1)] ?? null;
            if ($value) {
                $filters[] = "{$name}={$value}";
            }
        }

        return '|' . implode('|', $filters) . '|';
    }
}