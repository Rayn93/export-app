<?php
declare(strict_types=1);

namespace App\Service\Utils;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;
final readonly class ExportMailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {}

    public function notifySuccess(string $recipient): void
    {
        $subject = "[FactFinder] Export completed successfully";
        $body = $this->getSuccessBodyMessage();
        $this->send($recipient, $subject, $body);
    }

    public function notifyFailure(string $recipient): void
    {
        $subject = "[FactFinder] Export failed";
        $body = $this->getFailureBodyMessage();
        $this->send($recipient, $subject, $body);
    }

    private function send(string $recipient, string $subject, string $body): void
    {
        try {
            $email = (new Email())
                ->from('fact-finder-noreply@fact-finder.com')
                ->to($recipient)
                ->subject($subject)
                ->text($body);

            $this->mailer->send($email);
            $this->logger->info("Notification e-mail sent to {$recipient}", ['subject' => $subject]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send notification e-mail: ' . $e->getMessage());
        }
    }

    private function getSuccessBodyMessage(): string
    {
        return  <<<BODY
The product export process was successful.

Best regards,
FactFinder Team

[This message is automatically generated. Please do not reply.]
BODY;
    }

    private function getFailureBodyMessage(): string
    {
        return  <<<BODY
Unfortunately, the export of products from your store to FactFinder failed. 
Please verify that the configuration information in the app is correct and try again.

Best regards,
FactFinder Team

[This message is automatically generated. Please do not reply.]
BODY;
    }
}