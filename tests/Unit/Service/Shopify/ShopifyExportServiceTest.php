<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service\Shopify;

use App\Service\Shopify\ShopifyExportService;
use App\Repository\ShopifyOauthTokenRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ShopifyExportServiceTest extends TestCase
{
    private HttpClientInterface $client;
    private ShopifyOauthTokenRepository $tokenRepo;
    private LoggerInterface $logger;
    private ShopifyExportService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(HttpClientInterface::class);
        $this->tokenRepo = $this->createMock(ShopifyOauthTokenRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new ShopifyExportService($this->client, $this->tokenRepo, $this->logger);
    }

    public function testStreamProductsYieldsData(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn([
            'data' => [
                'products' => [
                    'edges' => [
                        ['node' => ['id' => 'gid://shopify/Product/1', 'title' => 'Test']],
                    ],
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                ],
            ],
        ]);

        $this->client->method('request')->willReturn($mockResponse);
        $token = new class {public function getAccessToken() { return 'abc123'; }};
        $this->tokenRepo->method('findOneBy')->willReturn($token);
        $batches = iterator_to_array($this->service->streamProducts('shop.myshopify.com', '12345', 'en'));
        $this->assertCount(1, $batches);
        $this->assertEquals('Test', $batches[0][0]['title']);
    }

    public function testStreamProductsThrowsExceptionWhenNoToken(): void
    {
        $this->tokenRepo->method('findOneBy')->willReturn(null);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No access token found for shop');
        iterator_to_array($this->service->streamProducts('shop.myshopify.com', '12345', 'en'));
    }
}