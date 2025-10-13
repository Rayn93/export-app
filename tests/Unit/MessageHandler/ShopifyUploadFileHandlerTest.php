<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\ShopifyAppConfig;
use App\Message\ShopifyPushImportMessage;
use App\Message\ShopifyUploadFileMessage;
use App\MessageHandler\ShopifyUploadFileHandler;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Upload\UploadService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

final class ShopifyUploadFileHandlerTest extends TestCase
{
    private ShopifyAppConfigRepository&MockObject $configRepository;
    private UploadService&MockObject $uploadService;
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;
    private ShopifyUploadFileHandler $handler;

    protected function setUp(): void
    {
        $this->configRepository = $this->createMock(ShopifyAppConfigRepository::class);
        $this->uploadService = $this->createMock(UploadService::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new ShopifyUploadFileHandler(
            $this->configRepository,
            $this->uploadService,
            $this->bus,
            $this->logger
        );
    }

    public function testLogsErrorAndReturnsWhenConfigNotFound(): void
    {
        $message = new ShopifyUploadFileMessage('shop.myshopify.com', 101, '/tmp/file.csv', 'mail@shop.com');
        $this->configRepository->method('find')->with(101)->willReturn(null);
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Upload: shopify config not found for id 101', ['shop' => 'shop.myshopify.com']);

        $this->uploadService->expects($this->never())->method('uploadForShopifyConfig');
        $this->bus->expects($this->never())->method('dispatch');
        ($this->handler)($message);
    }

    public function testSuccessfulUploadDispatchesPushImportMessage(): void
    {
        $config = $this->createMock(ShopifyAppConfig::class);
        $config->method('getFfChannelName')->willReturn('default');
        $message = new ShopifyUploadFileMessage('okshop.myshopify.com', 7, '/tmp/export.csv', 'notify@ok.com');
        $this->configRepository->method('find')->with(7)->willReturn($config);
        $this->uploadService->expects($this->once())
            ->method('uploadForShopifyConfig')
            ->with($config, '/tmp/export.csv', 'export.productData.default.csv')
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Upload file succeeded', [
                'shop' => 'okshop.myshopify.com',
                'filename' => 'export.productData.default.csv',
            ]);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ShopifyPushImportMessage $msg) {
                return 'okshop.myshopify.com' === $msg->getShopDomain()
                    && 7 === $msg->getShopifyAppConfigId()
                    && 'notify@ok.com' === $msg->getMailForFailureNotification();
            }))
            ->willReturnCallback(fn ($m) => new Envelope($m));

        ($this->handler)($message);
    }

    public function testFailedUploadThrowsRuntimeException(): void
    {
        $config = $this->createMock(ShopifyAppConfig::class);
        $config->method('getFfChannelName')->willReturn('main');
        $message = new ShopifyUploadFileMessage('failshop.myshopify.com', 8, '/tmp/fail.csv', 'fail@shop.com');
        $this->configRepository->method('find')->willReturn($config);
        $this->uploadService->method('uploadForShopifyConfig')->willReturn(false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Upload file failed. Trying again.');
        ($this->handler)($message);
    }

    public function testLogsErrorAndThrowsWhenUploadThrowsException(): void
    {
        $config = $this->createMock(ShopifyAppConfig::class);
        $config->method('getFfChannelName')->willReturn('errorchannel');
        $message = new ShopifyUploadFileMessage('errshop.myshopify.com', 9, '/tmp/error.csv', 'err@shop.com');
        $this->configRepository->method('find')->willReturn($config);
        $this->uploadService->method('uploadForShopifyConfig')
            ->willThrowException(new \RuntimeException('Upload service crashed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Upload task failed: Upload service crashed'),
                $this->arrayHasKey('exception')
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Upload service crashed');
        ($this->handler)($message);
    }

    public function testDispatchesProperMessageStructure(): void
    {
        $config = $this->createMock(ShopifyAppConfig::class);
        $config->method('getFfChannelName')->willReturn('pl-channel');
        $message = new ShopifyUploadFileMessage('detailshop.myshopify.com', 12, '/tmp/pl.csv', 'user@shop.com');
        $this->configRepository->method('find')->willReturn($config);
        $this->uploadService->method('uploadForShopifyConfig')->willReturn(true);
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ShopifyPushImportMessage $msg) {
                return $msg instanceof ShopifyPushImportMessage
                    && 'detailshop.myshopify.com' === $msg->getShopDomain()
                    && 12 === $msg->getShopifyAppConfigId()
                    && 'user@shop.com' === $msg->getMailForFailureNotification();
            }))
            ->willReturnCallback(fn ($m) => new Envelope($m));

        ($this->handler)($message);
    }
}
