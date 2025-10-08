<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service\Utils;

use App\Service\Utils\ExportMailNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ExportMailNotificationServiceTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private ExportMailNotificationService $service;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new ExportMailNotificationService($this->mailer, $this->logger);
    }

    public function testNotifySuccessSendsEmailAndLogsInfo(): void
    {
        $recipient = 'test@example.com';
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($recipient) {
                $this->assertSame('fact-finder-noreply@fact-finder.com', $email->getFrom()[0]->getAddress());
                $this->assertSame($recipient, $email->getTo()[0]->getAddress());
                $this->assertSame('[FactFinder] Export completed successfully', $email->getSubject());
                $this->assertStringContainsString('The product export process was successful.', $email->getTextBody());
                return true;
            }));

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Notification e-mail sent to test@example.com'),
                $this->arrayHasKey('subject')
            );

        $this->service->notifySuccess($recipient);
    }

    public function testNotifyFailureSendsEmailAndLogsInfo(): void
    {
        $recipient = 'fail@example.com';
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($recipient) {
                $this->assertSame('[FactFinder] Export failed', $email->getSubject());
                $this->assertStringContainsString('export of products from your store to FactFinder failed', $email->getTextBody());
                return true;
            }));

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Notification e-mail sent to fail@example.com'),
                $this->arrayHasKey('subject')
            );

        $this->service->notifyFailure($recipient);
    }

    public function testSendLogsErrorWhenMailerThrowsException(): void
    {
        $recipient = 'broken@example.com';
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new class('Mailer failed') extends \Exception {});

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to send notification e-mail: Mailer failed'));

        $this->service->notifyFailure($recipient);
    }

    public function testSuccessAndFailureBodiesAreCorrect(): void
    {
        $ref = new \ReflectionClass($this->service);
        $successBody = $ref->getMethod('getSuccessBodyMessage');
        $successBody->setAccessible(true);
        $successText = $successBody->invoke($this->service);
        $this->assertStringContainsString('The product export process was successful.', $successText);
        $this->assertStringContainsString('FactFinder Team', $successText);
        $failureBody = $ref->getMethod('getFailureBodyMessage');
        $failureBody->setAccessible(true);
        $failureText = $failureBody->invoke($this->service);
        $this->assertStringContainsString('export of products from your store to FactFinder failed', $failureText);
        $this->assertStringContainsString('FactFinder Team', $failureText);
    }
}
