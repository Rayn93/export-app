<?php

declare(strict_types=1);

namespace App\Message;

readonly class SendExportMailNotificationMessage
{
    public function __construct(private string $recipientEmail, private string $status)
    {
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
