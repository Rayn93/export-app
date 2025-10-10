<?php
declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Config\Enum\Protocol;
use App\Controller\ShopifyConfigController;
use App\Entity\ShopifyAppConfig;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Shopify\ShopifyApiService;
use App\Service\Utils\PasswordEncryptor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class ShopifyConfigControllerTest extends TestCase
{
    private ShopifyAppConfigRepository&MockObject $repository;
    private PasswordEncryptor&MockObject $encryptor;
    private ShopifyApiService&MockObject $shopifyApiService;
    private ShopifyConfigController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ShopifyAppConfigRepository::class);
        $this->encryptor = $this->createMock(PasswordEncryptor::class);
        $this->shopifyApiService = $this->createMock(ShopifyApiService::class);
        $this->controller = $this->getMockBuilder(ShopifyConfigController::class)
            ->onlyMethods(['render', 'redirectToRoute', 'addFlash', 'getParameter'])
            ->getMock();
    }

    public function testReturns400WhenShopMissing(): void
    {
        $request = new Request();
        $response = $this->controller->index($request, $this->repository, $this->encryptor, $this->shopifyApiService);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Missing shop parameter', $response->getContent());
    }

    public function testRendersTemplateWhenNoConfigExists(): void
    {
        $request = new Request(['shop' => 'test.myshopify.com', 'host' => 'abc']);
        $shopifyAppConfig = ShopifyAppConfig::createEmptyForShop('test.myshopify.com');

        $this->repository->method('findOneBy')->willReturn(null);
        $this->shopifyApiService->method('getSalesChannels')->willReturn(['web', 'store']);
        $this->shopifyApiService->method('getLanguages')->willReturn(['en', 'pl']);

        $this->controller->expects($this->once())->method('getParameter')
            ->with('shopify_client_id')
            ->willReturn('client123');

        $this->controller->expects($this->once())->method('render')
            ->with('shopify_config/index.html.twig', $this->callback(function ($context) use ($shopifyAppConfig) {
                return $context['shop'] === 'test.myshopify.com'
                    && $context['host'] === 'abc'
                    && $context['shopify_client_id'] === 'client123'
                    && $context['salesChannels'] === ['web', 'store']
                    && $context['languages'] === ['en', 'pl']
                    && $context['config'] instanceof ShopifyAppConfig;
            }))
            ->willReturn(new Response('OK'));

        $response = $this->controller->index($request, $this->repository, $this->encryptor, $this->shopifyApiService);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRendersTemplateWhenConfigExists(): void
    {
        $request = new Request(['shop' => 'test.myshopify.com']);
        $config = new ShopifyAppConfig();
        $config->setShopDomain('test.myshopify.com');
        $this->repository->method('findOneBy')->willReturn($config);
        $this->shopifyApiService->method('getSalesChannels')->willReturn(['web']);
        $this->shopifyApiService->method('getLanguages')->willReturn(['en']);
        $this->controller->expects($this->once())->method('getParameter')->willReturn('clientXYZ');
        $this->controller->expects($this->once())->method('render')
            ->with('shopify_config/index.html.twig', $this->arrayHasKey('config'))
            ->willReturn(new Response('Rendered'));

        $response = $this->controller->index($request, $this->repository, $this->encryptor, $this->shopifyApiService);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Rendered', $response->getContent());
    }

    public function testHandlesPostRequestAndRedirects(): void
    {
        $request = new Request(
            ['shop' => 'test.myshopify.com', 'host' => 'abc'],
            [
                'protocol' => Protocol::SFTP->value,
                'server_url' => 'ftp.example.com',
                'port' => '22',
                'username' => 'user',
                'root_directory' => '/data',
                'private_key_content' => 'key123',
                'key_passphrase' => 'secret',
                'ff_channel_name' => 'test-channel',
                'ff_api_server_url' => 'https://api.ff.com',
                'ff_api_username' => 'ffuser',
                'ff_api_password' => 'ffpass',
                'notification_email' => 'info@example.com',
            ],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'POST']
        );

        $config = ShopifyAppConfig::createEmptyForShop('test.myshopify.com');
        $this->repository->method('findOneBy')->willReturn($config);
        $this->encryptor->method('encrypt')->willReturnMap([
            ['secret', 'ENC(secret)'],
            ['ffpass', 'ENC(ffpass)'],
        ]);

        $this->repository->expects($this->once())->method('save')->with($this->isInstanceOf(ShopifyAppConfig::class), true);
        $this->controller->expects($this->once())->method('addFlash')->with('success', 'Configuration saved successfully!');
        $this->controller->expects($this->once())->method('redirectToRoute')
            ->with('shopify_config', ['shop' => 'test.myshopify.com', 'host' => 'abc'])
            ->willReturn(new RedirectResponse('/shopify/config?shop=test.myshopify.com'));

        $response = $this->controller->index($request, $this->repository, $this->encryptor, $this->shopifyApiService);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/shopify/config?shop=test.myshopify.com', $response->getTargetUrl());
        $this->assertSame(Protocol::SFTP, $config->getProtocol());
        $this->assertSame('ftp.example.com', $config->getServerUrl());
        $this->assertSame('user', $config->getUsername());
        $this->assertSame('ENC(secret)', $config->getKeyPassphrase());
        $this->assertSame('ENC(ffpass)', $config->getFfApiPassword());
    }
}
