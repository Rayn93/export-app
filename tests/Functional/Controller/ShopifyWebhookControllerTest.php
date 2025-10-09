<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\ShopifyWebhookController;
use App\Service\Shopify\ShopifyUninstallService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

final class ShopifyWebhookControllerTest extends TestCase
{
    private ShopifyUninstallService $uninstallService;
    private LoggerInterface $logger;
    private ShopifyWebhookController $controller;
    private string $clientSecret = 'test-secret';

    protected function setUp(): void
    {
        $this->uninstallService = $this->createMock(ShopifyUninstallService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new ShopifyWebhookController(
            $this->uninstallService,
            $this->logger,
            $this->clientSecret
        );
    }

    public function testHandleUninstallReturnsBadRequestWhenMissingHeaders(): void
    {
        $request = new Request([], [], [], [], [], [], '{"shop":"example.myshopify.com"}');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Shopify uninstall webhook missing required headers',
                $this->arrayHasKey('headers')
            );

        $response = $this->controller->handleUninstall($request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('Missing headers', $response->getContent());
    }

    public function testHandleUninstallReturnsUnauthorizedWhenHmacInvalid(): void
    {
        $rawBody = '{"shop":"example.myshopify.com"}';
        $request = new Request([], [], [], [], [], [], $rawBody);
        $request->headers->set('X-Shopify-Hmac-Sha256', 'invalid-hmac');
        $request->headers->set('X-Shopify-Shop-Domain', 'example.myshopify.com');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Shopify uninstall webhook HMAC verification failed',
                ['shop' => 'example.myshopify.com']
            );

        $response = $this->controller->handleUninstall($request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Invalid HMAC', $response->getContent());
    }

    public function testHandleUninstallReturnsOkWhenValidHmac(): void
    {
        $rawBody = '{"shop":"example.myshopify.com"}';
        $calculatedHmac = base64_encode(hash_hmac('sha256', $rawBody, $this->clientSecret, true));
        $request = new Request([], [], [], [], [], [], $rawBody);
        $request->headers->set('X-Shopify-Hmac-Sha256', $calculatedHmac);
        $request->headers->set('X-Shopify-Shop-Domain', 'example.myshopify.com');

        $this->uninstallService
            ->expects($this->once())
            ->method('removeDataForShop')
            ->with('example.myshopify.com');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Shopify uninstall processed successfully', ['shop' => 'example.myshopify.com']);

        $response = $this->controller->handleUninstall($request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function testHandleUninstallLogsErrorWhenExceptionThrown(): void
    {
        $rawBody = '{"shop":"example.myshopify.com"}';
        $calculatedHmac = base64_encode(hash_hmac('sha256', $rawBody, $this->clientSecret, true));
        $request = new Request([], [], [], [], [], [], $rawBody);
        $request->headers->set('X-Shopify-Hmac-Sha256', $calculatedHmac);
        $request->headers->set('X-Shopify-Shop-Domain', 'example.myshopify.com');

        $this->uninstallService
            ->expects($this->once())
            ->method('removeDataForShop')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Error while processing uninstall webhook',
                $this->arrayHasKey('exception')
            );

        $response = $this->controller->handleUninstall($request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }
}
