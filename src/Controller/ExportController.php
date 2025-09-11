<?php
declare(strict_types=1);

namespace App\Controller;

use App\Config\Enum\Protocol;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Export\FactFinderExporter;
use App\Service\ShopifyRequestValidator;
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
    public function __construct(
        private readonly LoggerInterface $factfinderLogger,
        private readonly FactFinderExporter $factFinderExporter,
    ) {
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
            $this->factfinderLogger->error("Executed export without configuration for: $shop.");

            return $this->redirectToRoute('app_shopify_config', $request->query->all());
        }

        // Pobierz dane FTP z konfiguracji
        $ftpHost = $shopifyAppConfig->getServerUrl();
        $ftpUsername = $shopifyAppConfig->getUsername();
        $ftpPrivateKey = $shopifyAppConfig->getPrivateKeyContent();
        $ftpPassphrase = $shopifyAppConfig->getKeyPassphrase();
        $ftpPort = $shopifyAppConfig->getPort() ?: 21;
        $ftpPath = $shopifyAppConfig->getRootDirectory() ?: '/';
        $useSftp = $shopifyAppConfig->getProtocol() === Protocol::SFTP;

        if (!$ftpHost || !$ftpUsername || !$ftpPrivateKey) {
            $this->addFlash('error', 'FTP/SFTP credentials are missing.');

            return $this->redirectToRoute('app_shopify_config', $request->query->all());
        }

        $file = $this->factFinderExporter->export($shop);
        $filename = 'shopify_products_export_' . date('Ymd_His') . '.csv';
        $success = $uploadService->uploadFile($file, $filename, $ftpHost, $ftpUsername, $ftpPrivateKey, $ftpPassphrase, $ftpPort, $ftpPath, $useSftp);

        if ($success) {
            $this->addFlash('success', 'Products exported and uploaded to FTP/SFTP successfully.');
        } else {
            $this->addFlash('error', 'Failed to upload file to FTP/SFTP.');
            $this->factfinderLogger->error("Failed to upload file to FTP/SFTP for shop: $shop.");
        }

        return $this->redirectToRoute('app_shopify_config', $request->query->all());
    }
}