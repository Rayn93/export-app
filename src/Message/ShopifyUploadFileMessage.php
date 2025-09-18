<?php
declare(strict_types=1);

namespace App\Message;

final readonly class ShopifyUploadFileMessage
{
    public function __construct(
        private string $shopDomain,
        private int $shopifyAppConfigId,
        private string $filePath,
        private string $mailForFailureNotification
    ) {
    }

    public function getShopDomain(): string
    {
        return $this->shopDomain;
    }

    public function getShopifyAppConfigId(): int
    {
        return $this->shopifyAppConfigId;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMailForFailureNotification(): string
    {
        return $this->mailForFailureNotification;
    }
}
