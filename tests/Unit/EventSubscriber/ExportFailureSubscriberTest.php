<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ExportFailureSubscriber;
use App\Message\SendExportMailNotificationMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class ExportFailureSubscriberTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;
    private ExportFailureSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new ExportFailureSubscriber($this->bus, $this->logger);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ExportFailureSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(WorkerMessageFailedEvent::class, $events);
        $this->assertSame(['onMessageFailed', 10], $events[WorkerMessageFailedEvent::class]);
    }

    public function testDoesNothingIfMessageHasNoMailMethod(): void
    {
        $message = new class () {
        };
        $envelope = new Envelope($message);
        $event = new WorkerMessageFailedEvent($envelope, 'transport', new \RuntimeException('test'));
        $this->bus->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('info');
        $this->subscriber->onMessageFailed($event);
    }

    public function testDoesNothingIfRetriesBelowMax(): void
    {
        $message = new class () {
            public function getMailForFailureNotification(): string
            {
                return 'test@example.com';
            }
        };

        $stamp = new RedeliveryStamp(1);
        $envelope = (new Envelope($message))->with($stamp);
        $event = new WorkerMessageFailedEvent($envelope, 'transport', new \RuntimeException('test'));
        $this->bus->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('info');
        $this->subscriber->onMessageFailed($event);
    }

    public function testDispatchesNotificationWhenMaxRetriesReached(): void
    {
        $email = 'fail@example.com';
        $message = new class ($email) {
            public function __construct(private string $email)
            {
            }

            public function getMailForFailureNotification(): string
            {
                return $this->email;
            }
        };

        $stamp = new RedeliveryStamp(3);
        $envelope = (new Envelope($message))->with($stamp);
        $event = new WorkerMessageFailedEvent($envelope, 'transport', new \RuntimeException('test'));

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Max retries reached, dispatching failure notification email');

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendExportMailNotificationMessage $msg) use ($email) {
                return $msg->getRecipientEmail() === $email && 'failure' === $msg->getStatus();
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->subscriber->onMessageFailed($event);
    }

    public function testDoesNothingIfNoRedeliveryStamp(): void
    {
        $message = new class () {
            public function getMailForFailureNotification(): string
            {
                return 'no-retry@example.com';
            }
        };

        $envelope = new Envelope($message);
        $event = new WorkerMessageFailedEvent($envelope, 'transport', new \RuntimeException('test'));
        $this->bus->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('info');
        $this->subscriber->onMessageFailed($event);
    }
}
