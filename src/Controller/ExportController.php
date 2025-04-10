<?php
declare(strict_types=1);

namespace App\Controller;

use App\Config\Enum\Protocol;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Export\CsvGenerator;
use App\Service\ShopifyRequestValidator;
use App\Service\ShopifyService;
use App\Service\Upload\UploadService;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    #[Route('/shopify/export/products/', name: 'shopify_export_products', methods: ['POST'])]
    public function exportProducts(
        Request $request,
        ShopifyAppConfigRepository $shopifyAppConfigRepository,
        ShopifyRequestValidator $validator,
        CsvGenerator $csvGenerator,
        UploadService $uploadService,
    ): Response {
        if (!$validator->validateShopifyRequest($request)) {
            return new Response('Unauthorized', 401);
        }

        $shop = $request->query->get('shop');

        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        // Pobierz konfigurację aplikacji
        $shopifyAppConfig = $shopifyAppConfigRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyAppConfig) {
            $this->addFlash('error', 'Configuration not found for this shop.');
            return $this->redirectToRoute('app_shopify_config', $request->query->all());
        }

        // Pobierz dane FTP z konfiguracji
        $ftpHost = $shopifyAppConfig->getServerUrl();
        $ftpUsername = $shopifyAppConfig->getUsername();
        $ftpPassword = $shopifyAppConfig->getKeyPassphrase();
        $ftpPort = $shopifyAppConfig->getPort() ?: 21;
        $ftpPath = $shopifyAppConfig->getRootDirectory() ?: '/';
        $useSftp = $shopifyAppConfig->getProtocol() === Protocol::SFTP;

        if (!$ftpHost || !$ftpUsername || !$ftpPassword) {
            $this->addFlash('error', 'FTP/SFTP credentials are missing.');

            return $this->redirectToRoute('app_shopify_config', $request->query->all());
        }

        try {
            $products = $this->shopifyService->getProducts($shop);
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                $this->logger->warning('Access token expired for shop: ' . $shop);
                // Token jest nieważny – przekieruj do autoryzacji
                return $this->redirectToRoute('shopify_install', ['shop' => $shop]);
            }

            $this->addFlash('error', 'Failed to fetch products from Shopify.');

            return $this->redirectToRoute('app_shopify_config', $request->query->all());
        }

        // Generuj plik CSV
        $csvData = $csvGenerator->generate($products, ['id', 'title', 'price']);

        // Wyślij plik na serwer FTP/SFTP
        $filename = 'shopify_products_export_' . date('Ymd_His') . '.csv';
        $success = $uploadService->upload($csvData, $filename, $ftpHost, $ftpUsername, $ftpPassword, $ftpPort, $ftpPath, $useSftp);

        if ($success) {
            $this->addFlash('success', 'Products exported and uploaded to FTP/SFTP successfully.');
        } else {
            $this->addFlash('error', 'Failed to upload file to FTP/SFTP.');
        }

        return $this->redirectToRoute('app_shopify_config', $request->query->all());
    }
}