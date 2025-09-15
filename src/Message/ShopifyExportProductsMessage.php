<?php
declare(strict_types=1);

namespace App\Message;

final class ShopifyExportProductsMessage
{
    private string $shopDomain;
    private int $shopifyAppConfigId;

    public function __construct(string $shopDomain, int $shopifyAppConfigId)
    {
        $this->shopDomain = $shopDomain;
        $this->shopifyAppConfigId = $shopifyAppConfigId;
    }

    public function getShopDomain(): string
    {
        return $this->shopDomain;
    }

    public function getShopifyAppConfigId(): int
    {
        return $this->shopifyAppConfigId;
    }
}