<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\AuthController;
use App\Repository\ShopifyOauthTokenRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AuthControllerTest extends TestCase
{
    private ShopifyOauthTokenRepository $repository;
    private LoggerInterface $logger;
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ShopifyOauthTokenRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->controller = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->repository, $this->logger])
            ->onlyMethods(['getParameter', 'redirect', 'redirectToRoute'])
            ->getMock();
    }

    public function testInstallReturns400IfShopMissing(): void
    {
        $request = new Request();
        $session = $this->createMock(SessionInterface::class);
        $response = $this->controller->install($request, $session);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing shop parameter', $response->getContent());
    }

    public function testInstallRedirectsToShopifyAuthorizationUrl(): void
    {
        $request = new Request(['shop' => 'testshop.myshopify.com']);
        $session = $this->createMock(SessionInterface::class);
        $this->controller->method('getParameter')->willReturnMap([
            ['shopify_client_id', 'client_id'],
            ['shopify_client_secret', 'client_secret'],
            ['shopify_redirect_uri', 'https://example.com/callback'],
        ]);

        $this->logger->expects($this->exactly(2))->method('info');
        $this->controller->expects($this->once())
            ->method('redirect')
            ->with($this->callback(
                fn ($url) => str_contains($url, 'https://testshop.myshopify.com/admin/oauth/authorize')
            ))
            ->willReturn(new RedirectResponse('https://auth-url'));

        $response = $this->controller->install($request, $session);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('https://auth-url', $response->getTargetUrl());
    }

    public function testCallbackReturns400ForInvalidState(): void
    {
        $request = new Request(['shop' => 'testshop.myshopify.com', 'state' => 'invalid']);
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('oauth2_state')->willReturn('expected');
        $this->logger->expects($this->once())->method('error');
        $response = $this->controller->callback($request, $session);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Invalid OAuth state', $response->getContent());
    }
}
