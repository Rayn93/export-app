<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ShopifyAppConfigRepository;
use App\Service\ShopifyRequestValidator;
use App\Service\Upload\UploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class TestConnectionController extends AbstractController
{
    #[Route('/shopify/config/test-ftp-connection', name: 'app_shopify_test_ftp_connection', methods: ['POST'])]
    public function testFtpConnection(
        Request $request,
        ShopifyRequestValidator $validator,
        ShopifyAppConfigRepository $shopifyAppConfigRepository,
        UploadService $uploadService
    ): Response {
        if (!$validator->validateShopifyRequest($request)) {
            return new Response('Unauthorized', 401);
        }

        $shop = $request->query->get('shop');

        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        $shopifyAppConfig = $shopifyAppConfigRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyAppConfig) {
            return new Response('Config not found', 404);
        }

        $testFile = tempnam(sys_get_temp_dir(), 'ftp_test_');
        file_put_contents($testFile, 'test');
        $success = $uploadService->uploadForShopifyConfig($shopifyAppConfig, $testFile, 'test_connection.txt');

        if ($success) {
            $this->addFlash('success', "FTP/SFTP connection successful!");
        } else {
            $this->addFlash('error', "FTP/SFTP Connection failed. Please check your FTP/SFTP credentials and data.");
        }

        return $this->redirectToRoute('app_shopify_config', $request->query->all());
    }

}