<?php
declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\ShopifyAppConfig;
use App\Message\SendExportMailNotificationMessage;
use App\Message\ShopifyPushImportMessage;
use App\MessageHandler\ShopifyPushImportHandler;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Communication\PushImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

final class ShopifyPushImportHandlerTest extends TestCase
{
    private ShopifyAppConfigRepository&MockObject $configRepository;
    private PushImportService&MockObject $pushImportService;
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;
    private ShopifyPushImportHandler $handler;

    protected function setUp(): void
    {
        $this->configRepository = $this->createMock(ShopifyAppConfigRepository::class);
        $this->pushImportService = $this->createMock(PushImportService::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ShopifyPushImportHandler(
            $this->configRepository,
            $this->pushImportService,
            $this->bus,
            $this->logger
        );
    }

    public function testLogsErrorAndReturnsWhenConfigNotFound(): void
    {
        $message = new ShopifyPushImportMessage('shop.myshopify.com', 42, 'mail@test.com');
        $this->configRepository->method('find')->with(42)->willReturn(null);
        $this->logger->expects($this->once())
            ->method('error')
            ->with('PushImport: shopify config not found for id 42', ['shop' => 'shop.myshopify.com']);

        $this->pushImportService->expects($this->never())->method('execute');
        $this->bus->expects($this->never())->method('dispatch');
        ($this->handler)($message);
    }

    public function testExecutesPushImportAndDispatchesSuccessMail(): void
    {
        $message = new ShopifyPushImportMessage('testshop.myshopify.com', 7, 'success@test.com');
        $config = $this->createMock(ShopifyAppConfig::class);
        $this->configRepository->method('find')->with(7)->willReturn($config);
        $this->pushImportService->expects($this->once())->method('execute')->with($config);
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Push import executed successfully', ['shop' => 'testshop.myshopify.com']);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendExportMailNotificationMessage $msg) {
                return $msg->getRecipientEmail() === 'success@test.com'
                    && $msg->getStatus() === 'success';
            }))
            ->willReturnCallback(fn($m) => new Envelope($m));

        ($this->handler)($message);
    }

    public function testLogsErrorAndThrowsWhenPushImportFails(): void
    {
        $message = new ShopifyPushImportMessage('failshop.myshopify.com', 5, 'fail@test.com');
        $config = $this->createMock(ShopifyAppConfig::class);
        $this->configRepository->method('find')->with(5)->willReturn($config);
        $this->pushImportService->method('execute')->willThrowException(new \RuntimeException('API timeout'));
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Push import failed: API timeout'),
                $this->arrayHasKey('exception')
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API timeout');
        ($this->handler)($message);
    }

    public function testDispatchesProperMessageStructure(): void
    {
        $message = new ShopifyPushImportMessage('detailshop.myshopify.com', 15, 'notify@me.com');
        $config = $this->createMock(ShopifyAppConfig::class);
        $this->configRepository->method('find')->willReturn($config);
        $this->pushImportService->expects($this->once())->method('execute')->with($config);
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($msg) {
                return $msg instanceof SendExportMailNotificationMessage
                    && $msg->getRecipientEmail() === 'notify@me.com'
                    && $msg->getStatus() === 'success';
            }))
            ->willReturnCallback(fn($m) => new Envelope($m));

        ($this->handler)($message);
    }
}
