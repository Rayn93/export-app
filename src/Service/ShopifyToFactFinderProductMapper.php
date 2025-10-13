<?php

declare(strict_types=1);

namespace App\Service;

final class ShopifyToFactFinderProductMapper
{
    public function map(array $shopifyProducts, string $shopDomain): \Generator
    {
        foreach ($shopifyProducts as $p) {
            $masterId     = (string) $p['legacyResourceId'];
            $title        = $this->getTranslatedValue($p['translations'] ?? [], 'title', $p['title'] ?? '');
            $description  = $this->getTranslatedValue(
                $p['translations'] ?? [],
                'body_html',
                $p['descriptionHtml'] ?? ''
            );
            $brand        = $p['vendor'] ?? '';
            $deeplink     = $this->buildDeeplink($shopDomain, $p['handle'] ?? '', $p['onlineStoreUrl'] ?? null);
            $description  = trim(strip_tags($description));
            $imageUrl     = $p['images']['edges'][0]['node']['url'] ?? '';
            $categoryPath = $this->buildCategoryPathFromTaxonomy($p['category'] ?? null);
            $variantEdges = $p['variants']['edges'] ?? [];
            $variants     = array_map(static fn (array $e) => $e['node'], $variantEdges);
            $hasMultiple = count($variants) > 1;
            $masterPrice = isset($variants[0]['price']) ? (string) $variants[0]['price'] : '';

            // master produkt
            $masterRow = [
                'ProductNumber'    => $masterId,
                'Master'           => $masterId,
                'Name'             => $title,
                'Brand'            => $brand,
                'CategoryPath'     => $categoryPath,
                'Deeplink'         => $deeplink,
                'Description'      => $description,
                'ImageUrl'         => $imageUrl,
                'Price'            => $masterPrice,
                'FilterAttributes' => $this->buildMasterFilterAttributes($variants),
            ];

            yield $masterRow;

            // warianty
            if ($hasMultiple) {
                foreach ($variants as $v) {
                    $variantId = (string) $v['legacyResourceId'];
                    $vTitle    = $this->getTranslatedValue($v['translations'] ?? [], 'option1', $v['title'] ?? '');
                    $name      = trim($title . ' ' . ('Default Title' !== $vTitle ? $vTitle : ''));
                    $price     = isset($v['price']) ? (string) $v['price'] : '';

                    yield [
                        'ProductNumber'    => $variantId,
                        'Master'           => $masterId,
                        'Name'             => $name,
                        'Brand'            => $brand,
                        'CategoryPath'     => $categoryPath,
                        'Deeplink'         => $deeplink,
                        'Description'      => $description,
                        'ImageUrl'         => $imageUrl,
                        'Price'            => $price,
                        'FilterAttributes' => $this->buildVariantFilterAttributes($v['selectedOptions'] ?? []),
                    ];
                }
            }
        }
    }

    private function getTranslatedValue(array $translations, string $key, ?string $fallback): ?string
    {
        foreach ($translations as $t) {
            if (($t['key'] ?? null) === $key && !empty($t['value'])) {
                return $t['value'];
            }
        }

        return $fallback;
    }

    private function buildCategoryPathFromTaxonomy(?array $category): string
    {
        $fullName = $category['fullName'] ?? '';

        if ('' === $fullName) {
            return 'Uncategorized';
        }

        if (str_contains($fullName, ' > ')) {
            $fullName = str_replace(' > ', '/', $fullName);
        }

        return  str_replace('%2F', '/', urlencode($fullName));
    }

    private function buildMasterFilterAttributes(array $variants): string
    {
        $byName = [];

        foreach ($variants as $v) {
            foreach ($v['selectedOptions'] ?? [] as $opt) {
                $name  = $opt['name']  ?? '';
                $value = $opt['value'] ?? '';

                if ('' === $name || '' === $value || ('Title' === $name && 'Default Title' === $value)) {
                    continue;
                }

                $byName[$name] ??= [];

                if (!in_array($value, $byName[$name], true)) {
                    $byName[$name][] = $value;
                }
            }
        }

        $pairs = [];

        foreach ($byName as $name => $values) {
            foreach ($values as $v) {
                $pairs[] = "{$name}={$v}";
            }
        }

        return $pairs ? '|' . implode('|', $pairs) . '|' : '';
    }

    /** Atrybuty konkretnego wariantu */
    private function buildVariantFilterAttributes(array $selectedOptions): string
    {
        $pairs = [];

        foreach ($selectedOptions as $opt) {
            $name  = $opt['name']  ?? '';
            $value = $opt['value'] ?? '';

            if ('' === $name || '' === $value || ('Title' === $name && 'Default Title' === $value)) {
                continue;
            }

            $pairs[] = "{$name}={$value}";
        }

        return $pairs ? '|' . implode('|', $pairs) . '|' : '';
    }

    private function buildDeeplink(string $shopDomain, string $handle, ?string $onlineStoreUrl): string
    {
        return $handle ? "https://{$shopDomain}/products/{$handle}" : $onlineStoreUrl ?? '';
    }
}
