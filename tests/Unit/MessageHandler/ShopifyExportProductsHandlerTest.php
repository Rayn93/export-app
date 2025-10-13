<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\ShopifyExportProductsMessage;
use App\Message\ShopifyUploadFileMessage;
use App\MessageHandler\ShopifyExportProductsHandler;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Export\FactFinderExporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

final class ShopifyExportProductsHandlerTest extends TestCase
{
    private ShopifyAppConfigRepository&MockObject $configRepository;
    private FactFinderExporter&MockObject $exporter;
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;
    private ShopifyExportProductsHandler $handler;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->configRepository = $this->createMock(ShopifyAppConfigRepository::class);
        $this->exporter = $this->createMock(FactFinderExporter::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ShopifyExportProductsHandler(
            $this->configRepository,
            $this->exporter,
            $this->bus,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }

        parent::tearDown();
    }

    public function testLogsErrorAndReturnsWhenConfigNotFound(): void
    {
        $message = new ShopifyExportProductsMessage(
            'testshop.myshopify.com',
            999,
            'salesChannel1',
            'en',
            'mail@test.com'
        );
        $this->configRepository->method('find')->with(999)->willReturn(null);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Export: shopify config not found for id 999', ['shop' => 'testshop.myshopify.com']);

        $this->exporter->expects($this->never())->method('export');
        $this->bus->expects($this->never())->method('dispatch');
        ($this->handler)($message);
    }

    public function testDispatchesUploadMessageWhenExportSuccessful(): void
    {
        $message = new ShopifyExportProductsMessage('demo.myshopify.com', 1, 'sc1', 'en', 'mail@example.com');
        $tempFile = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($tempFile, str_repeat('x', 2000)); // 2KB
        $this->tempFiles[] = $tempFile;
        $this->configRepository->method('find')->with(1)->willReturn(new \stdClass());
        $this->exporter->method('export')->willReturn($tempFile);
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ShopifyUploadFileMessage $msg) use ($tempFile) {
                return method_exists($msg, 'getTempFile') ? $msg->getTempFile() === $tempFile : true;
            }))
            ->willReturnCallback(fn ($message) => new Envelope($message));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Shopify export file created successfully', ['shop' => 'demo.myshopify.com']);

        ($this->handler)($message);
    }

    public function testThrowsWhenFileTooSmall(): void
    {
        $message = new ShopifyExportProductsMessage('smallshop.myshopify.com', 2, 'sc1', 'en', null);
        $tempFile = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($tempFile, 'x'); // 1 byte
        $this->tempFiles[] = $tempFile;

        $this->configRepository->method('find')->with(2)->willReturn(new \stdClass());
        $this->exporter->method('export')->willReturn($tempFile);
        $calls = [];
        $this->logger->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function ($message, $context = []) use (&$calls) {
                $calls[] = [$message, $context];
            });

        try {
            ($this->handler)($message);
            $this->fail('Expected RuntimeException to be thrown due to small file');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Exported file too small', $e->getMessage());
        }

        $this->assertCount(2, $calls);
        $this->assertSame('Exported file too small, problem with product export', $calls[0][0]);
        $this->assertArrayHasKey('shop', $calls[0][1]);
        $this->assertStringContainsString(
            'Export file cannot be created. Error: Exported file too small',
            $calls[1][0]
        );
        $this->assertArrayHasKey('exception', $calls[1][1]);
    }

    public function testLogsErrorAndThrowsWhenExporterFails(): void
    {
        $message = new ShopifyExportProductsMessage('failshop.myshopify.com', 3, 'sc', 'de', 'mail@x.com');
        $this->configRepository->method('find')->with(3)->willReturn(new \stdClass());
        $this->exporter->method('export')
            ->willThrowException(new \RuntimeException('Boom'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Export file cannot be created. Error: Boom'),
                $this->arrayHasKey('exception')
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Boom');
        ($this->handler)($message);
    }
}
