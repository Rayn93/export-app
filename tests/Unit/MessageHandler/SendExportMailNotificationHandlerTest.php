<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\SendExportMailNotificationMessage;
use App\MessageHandler\SendExportMailNotificationHandler;
use App\Service\Utils\ExportMailNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SendExportMailNotificationHandlerTest extends TestCase
{
    private ExportMailNotificationService $notificationService;
    private LoggerInterface $logger;
    private SendExportMailNotificationHandler $handler;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(ExportMailNotificationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new SendExportMailNotificationHandler(
            $this->notificationService,
            $this->logger
        );
    }

    public function testHandleSuccessMessageCallsNotifySuccess(): void
    {
        $message = $this->createConfiguredMock(SendExportMailNotificationMessage::class, [
            'getRecipientEmail' => 'test@example.com',
            'getStatus' => 'success',
        ]);

        $this->notificationService
            ->expects($this->once())
            ->method('notifySuccess')
            ->with('test@example.com');

        $this->notificationService
            ->expects($this->never())
            ->method('notifyFailure');

        $this->logger
            ->expects($this->never())
            ->method('error');

        ($this->handler)($message);
    }

    public function testHandleFailureMessageCallsNotifyFailure(): void
    {
        $message = $this->createConfiguredMock(SendExportMailNotificationMessage::class, [
            'getRecipientEmail' => 'fail@example.com',
            'getStatus' => 'failure',
        ]);

        $this->notificationService
            ->expects($this->once())
            ->method('notifyFailure')
            ->with('fail@example.com');

        $this->notificationService
            ->expects($this->never())
            ->method('notifySuccess');

        $this->logger
            ->expects($this->never())
            ->method('error');

        ($this->handler)($message);
    }

    public function testHandleThrowsExceptionAndLogsError(): void
    {
        $message = $this->createConfiguredMock(SendExportMailNotificationMessage::class, [
            'getRecipientEmail' => 'error@example.com',
            'getStatus' => 'success',
        ]);

        $this->notificationService
            ->expects($this->once())
            ->method('notifySuccess')
            ->willThrowException(new \RuntimeException('SMTP failure'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to send notification e-mail: SMTP failure'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP failure');

        ($this->handler)($message);
    }
}
