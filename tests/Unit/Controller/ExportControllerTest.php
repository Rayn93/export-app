<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ExportController;
use App\Repository\ShopifyAppConfigRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;

class ExportControllerTest extends TestCase
{
    private LoggerInterface|MockObject $logger;
    private ShopifyAppConfigRepository|MockObject $repository;
    private MessageBusInterface|MockObject $bus;
    private RouterInterface|MockObject $router;
    private ExportController $controller;
    private Session $session;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->repository = $this->createMock(ShopifyAppConfigRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->session = new Session(new MockArraySessionStorage());
        $this->controller = new ExportController($this->logger);
        $container = new class ($this->router, $this->session) implements ContainerInterface {
            private RouterInterface $router;
            private Session $session;

            public function __construct(RouterInterface $router, Session $session)
            {
                $this->router = $router;
                $this->session = $session;
            }

            public function get(string $id)
            {
                return match ($id) {
                    'router'  => $this->router,
                    'session' => $this->session,
                    default   => null,
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, ['router', 'session'], true);
            }
        };

        $this->controller->setContainer($container);
    }

    public function testExportProductsReturns400IfNoShopParameter(): void
    {
        $request = new Request();
        $response = $this->controller->exportProducts($request, $this->repository, $this->bus);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Missing shop parameter', $response->getContent());
    }
}
