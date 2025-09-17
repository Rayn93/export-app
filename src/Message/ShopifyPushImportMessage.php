<?php
declare(strict_types=1);

namespace App\Message;

final readonly class ShopifyPushImportMessage
{
    public function __construct(
        private string $shopDomain,
        private int $shopifyAppConfigId
    ) {}

    public function getShopDomain(): string
    {
        return $this->shopDomain;
    }

    public function getShopifyAppConfigId(): int
    {
        return $this->shopifyAppConfigId;
    }
}
