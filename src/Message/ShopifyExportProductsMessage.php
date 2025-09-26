<?php
declare(strict_types=1);

namespace App\Message;

final readonly class ShopifyExportProductsMessage
{
    public function __construct(
        private string $shopDomain,
        private int $shopifyAppConfigId,
        private string $salesChannelId,
        private string $locale,
        private ?string $mailForFailureNotification
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

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getMailForFailureNotification(): ?string
    {
        return $this->mailForFailureNotification;
    }
}