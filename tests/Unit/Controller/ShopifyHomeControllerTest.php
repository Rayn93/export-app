<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ShopifyHomeController;
use App\Repository\ShopifyOauthTokenRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShopifyHomeControllerTest extends TestCase
{
    private ShopifyOauthTokenRepository&MockObject $tokenRepository;
    private LoggerInterface&MockObject $logger;
    private ShopifyHomeController $controller;

    protected function setUp(): void
    {
        $this->tokenRepository = $this->createMock(ShopifyOauthTokenRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new ShopifyHomeController(
            $this->tokenRepository,
            $this->logger
        );
    }

    public function testReturns400WhenShopParameterMissing(): void
    {
        $request = new Request(); // brak parametru ?shop=
        $response = $this->controller->index($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Missing shop parameter', $response->getContent());
    }

    public function testRedirectsToConfigWhenTokenExists(): void
    {
        $request = new Request(['shop' => 'testshop.myshopify.com', 'foo' => 'bar']);

        $this->tokenRepository->method('count')
            ->with(['shopDomain' => 'testshop.myshopify.com'])
            ->willReturn(1);

        $controller = $this->getMockBuilder(ShopifyHomeController::class)
            ->setConstructorArgs([$this->tokenRepository, $this->logger])
            ->onlyMethods(['redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('shopify_config', ['shop' => 'testshop.myshopify.com', 'foo' => 'bar'])
            ->willReturn(new RedirectResponse('/shopify/config'));

        $response = $controller->index($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/shopify/config', $response->getTargetUrl());
    }

    public function testRedirectsToInstallWhenTokenDoesNotExist(): void
    {
        $request = new Request(['shop' => 'newshop.myshopify.com']);

        $this->tokenRepository->method('count')
            ->with(['shopDomain' => 'newshop.myshopify.com'])
            ->willReturn(0);

        $controller = $this->getMockBuilder(ShopifyHomeController::class)
            ->setConstructorArgs([$this->tokenRepository, $this->logger])
            ->onlyMethods(['redirectToRoute'])
            ->getMock();

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('shopify_install', ['shop' => 'newshop.myshopify.com'])
            ->willReturn(new RedirectResponse('/shopify/install'));

        $response = $controller->index($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/shopify/install', $response->getTargetUrl());
    }
}
