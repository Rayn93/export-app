<?php
declare(strict_types=1);

namespace App\Service\Export;

class CsvGenerator
{
    public function generate(array $products, array $fields): string
    {
        $output = fopen('php://temp', 'w+');

        // Nagłówki CSV
        fputcsv($output, $fields);

        // Dane produktów
        foreach ($products as $product) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = $product[$field] ?? '';
            }
            fputcsv($output, $row);
        }

        rewind($output);
        $csvData = stream_get_contents($output);
        fclose($output);

        return $csvData;
    }
}