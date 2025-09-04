<?php
declare(strict_types=1);

namespace App\Service\Export;

use App\Service\ShopifyService;
use App\Service\ShopifyToFactFinderProductMapper;

class FactFinderExporter
{
    public function __construct(
        private readonly ShopifyService           $shopifyService,
        private readonly ShopifyToFactFinderProductMapper  $mapper,
        private readonly string $kernelProjectDir
    ) {
    }

    public function export(string $shopDomain): string
    {
        $filename   = $this->createFilename($shopDomain);
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
        foreach ($this->shopifyService->streamProducts($shopDomain) as $batch) {

            foreach ($this->mapper->map($batch, $shopDomain) as $row) {
                fputcsv($file, $row, ';');
            }
        }

        fclose($file);

        return $filename;
    }

    private function createFilename($shopDomain): string
    {
        $dir = $this->kernelProjectDir . '/var/factfinder';

        if (!is_dir($dir)) {
            mkdir($dir);
        }

        if (!is_writable($dir)) {
            throw new \Exception('Directory ' . $dir . ' is not writable. Aborting');
        }

        return $dir . DIRECTORY_SEPARATOR . 'shopify_products_' . $shopDomain . '.csv';
    }
}
