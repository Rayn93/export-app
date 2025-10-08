<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendExportMailNotificationMessage;
use App\Service\Utils\ExportMailNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SendExportMailNotificationHandler
{
    public function __construct(
        private ExportMailNotificationService $notificationService,
        private LoggerInterface $factfinderLogger,
    ) {}

    public function __invoke(SendExportMailNotificationMessage $message): void
    {
        $recipient = $message->getRecipientEmail();

        try {
            if ($message->getStatus() === 'success') {
                $this->notificationService->notifySuccess($recipient);
            } else {
                $this->notificationService->notifyFailure($recipient);
            }
        } catch (\Throwable $e) {
            $this->factfinderLogger->error('Failed to send notification e-mail: ' . $e->getMessage());

            throw $e; // retry sending email
        }


    }
}