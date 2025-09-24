<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\ShopifyAppConfig;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\ShopifyRequestValidator;
use App\Service\Upload\UploadService;
use App\Service\Utils\PasswordEncryptor;
use Omikron\FactFinder\Communication\Client\ClientBuilder;
use Omikron\FactFinder\Communication\Credentials;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class TestConnectionController extends AbstractController
{
    public function __construct(
        private readonly ShopifyRequestValidator $validator,
        private readonly ShopifyAppConfigRepository $shopifyAppConfigRepository
    ) {
    }

    #[Route('/shopify/config/test-ftp-connection', name: 'shopify_test_ftp_connection', methods: ['POST'])]
    public function testFtpConnection(Request $request, UploadService $uploadService
    ): Response {
        $shopifyAppConfig = $this->verifyShopifyRequestAndGetConfig($request);

        if ($shopifyAppConfig instanceof Response) {
            return $shopifyAppConfig;
        }

        $testFile = tempnam(sys_get_temp_dir(), 'ftp_test_');
        file_put_contents($testFile, 'test');
        $success = $uploadService->uploadForShopifyConfig($shopifyAppConfig, $testFile, 'test_connection.txt');

        if ($success) {
            $this->addFlash('success', "FTP/SFTP connection successful!");
        } else {
            $this->addFlash('error', "FTP/SFTP Connection failed. Please check your FTP/SFTP credentials and data.");
        }

        return $this->redirectToRoute('shopify_config', $request->query->all());
    }

    #[Route('/shopify/config/test-api-connection', name: 'shopify_test_api_connection', methods: ['POST'])]

    public function testApiConnection(Request $request, ClientBuilder $clientBuilder, PasswordEncryptor $passwordEncryptor): Response
    {
        $shopifyAppConfig = $this->verifyShopifyRequestAndGetConfig($request);

        if ($shopifyAppConfig instanceof Response) {
            return $shopifyAppConfig;
        }

        $channelName = $shopifyAppConfig->getFfChannelName();
        $apiServerUrl = $shopifyAppConfig->getFfApiServerUrl();
        $apiUsername = $shopifyAppConfig->getFfApiUsername();
        $apiPassword = $shopifyAppConfig->getFfApiPassword();

        if (!empty($apiServerUrl) && !empty($apiUsername) && !empty($apiPassword)) {
            try {
                $client = $clientBuilder
                    ->withCredentials(new Credentials($apiUsername, $passwordEncryptor->decrypt($apiPassword)))
                    ->withServerUrl($shopifyAppConfig->getFfApiServerUrl())
                    ->withVersion('ng')
                    ->build();

                $endpoint = "rest/v5/records/{$channelName}/compare";
                $client->request('GET', $endpoint);
                $this->addFlash('success', "API Import connection successful!");
            } catch (\Exception $e) {
                $this->addFlash('error', "API Import credentials invalid");
            }
        } else {
            $this->addFlash('error', "Please provide API Import credentials to test the connection.");
        }

        return $this->redirectToRoute('shopify_config', $request->query->all());
    }

    private function verifyShopifyRequestAndGetConfig(Request $request): ShopifyAppConfig|Response
    {
        if (!$this->validator->validateShopifyRequest($request)) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $shop = $request->query->get('shop');

        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        $shopifyAppConfig = $this->shopifyAppConfigRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyAppConfig) {
            return new Response('Config not found', 404);
        }

        return $shopifyAppConfig;
    }
}