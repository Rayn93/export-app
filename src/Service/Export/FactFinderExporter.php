<?php
declare(strict_types=1);

namespace App\Service\Export;

use App\Service\Shopify\ShopifyExportService;
use App\Service\ShopifyToFactFinderProductMapper;

class FactFinderExporter
{
    public function __construct(
        private readonly ShopifyExportService $shopifyService,
        private readonly ShopifyToFactFinderProductMapper $mapper,
        private readonly string $kernelProjectDir
    ) {
    }

    public function export(string $shopDomain, string $salesChannel, string $locale): string
    {
        $filename   = $this->createFilename($shopDomain, $locale);
        $file = fopen($filename, 'w+');

        // Nagłówki CSV
        fputcsv($file, [
            'ProductNumber',
            'Master',
            'Name',
            'Brand',
            'CategoryPath',
            'Deeplink',
            'Description',
            'ImageUrl',
            'Price',
            'FilterAttributes'
        ], ';');

        // Strumieniowe pobieranie i mapowanie produktów
        foreach ($this->shopifyService->streamProducts($shopDomain, $salesChannel, $locale) as $batch) {

            foreach ($this->mapper->map($batch, $shopDomain) as $row) {
                fputcsv($file, $row, ';');
            }
        }

        fclose($file);

        return $filename;
    }

    private function createFilename($shopDomain, $locale): string
    {
        $dir = $this->kernelProjectDir . '/var/factfinder';

        if (!is_dir($dir)) {
            mkdir($dir);
        }

        if (!is_writable($dir)) {
            throw new \Exception('Directory ' . $dir . ' is not writable. Aborting');
        }

        return sprintf('%s/shopify_products_%s_%s.csv', $dir, $shopDomain, $locale);
    }
}
