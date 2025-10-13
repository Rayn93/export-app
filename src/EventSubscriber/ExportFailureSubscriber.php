<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Message\SendExportMailNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final readonly class ExportFailureSubscriber implements EventSubscriberInterface
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private MessageBusInterface $bus,
        private LoggerInterface $factfinderLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', 10],
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message  = $envelope->getMessage();

        if (!method_exists($message, 'getMailForFailureNotification')) {
            return;
        }

        $retryCount = $envelope->last(RedeliveryStamp::class)?->getRetryCount() ?? 0;
        $maxRetries = self::MAX_RETRIES;

        if ($retryCount >= $maxRetries) {
            $this->factfinderLogger->info('Max retries reached, dispatching failure notification email');
            $this->bus->dispatch(
                new SendExportMailNotificationMessage(
                    $message->getMailForFailureNotification(),
                    'failure'
                )
            );
        }
    }
}
