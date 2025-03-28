<?php

namespace App\Controller;

use App\Service\ShopifyService;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\Writer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExportController extends AbstractController
{
    public function __construct(private readonly ShopifyService $shopifyService, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws CannotInsertRecord
     * @throws Exception
     * @throws \Exception
     */
    #[Route('/export/products/{shop}', name: 'export_products')]
    public function exportProducts(string $shop): Response
    {
        try {
            $products = $this->shopifyService->getProducts($shop);

            // Generuj CSV
            $csvData = [];
            $csvData[] = ['ID', 'Title', 'Price']; // Nagłówki
            foreach ($products as $product) {
                $csvData[] = [
                    $product['id'],
                    $product['title'],
                    $product['variants'][0]['price'] ?? 'N/A',
                ];
            }

            $response = new Response();
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="products.csv"');

            $output = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);

            return $response;
        } catch (\Exception $e) {
            if ($e->getCode() === 401) {
                $this->logger->warning('Access token expired for shop: ' . $shop);
                // Token jest nieważny – przekieruj do autoryzacji
                return $this->redirectToRoute('shopify_install', ['shop' => $shop]);
            }

            $this->logger->error('Error exporting products: ' . $e->getMessage());

            return new Response('Error: ' . $e->getMessage(), 500);
        }
    }
}