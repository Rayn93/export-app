<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ShopifyOauthToken;
use PHPUnit\Framework\TestCase;

final class ShopifyOauthTokenTest extends TestCase
{
    public function testInitialValuesAreNull(): void
    {
        $token = new ShopifyOauthToken();
        $this->assertNull($token->getId());
        $this->assertNull($token->getShopDomain());
        $this->assertNull($token->getAccessToken());
        $this->assertNull($token->getCreatedAt());
        $this->assertNull($token->getUpdatedAt());
    }

    public function testSettersAndGettersWork(): void
    {
        $token = new ShopifyOauthToken();
        $now = new \DateTimeImmutable('2025-01-01 12:00:00');
        $updated = new \DateTimeImmutable('2025-01-02 15:30:00');
        $result = $token
            ->setShopDomain('test.myshopify.com')
            ->setAccessToken('shpat_123')
            ->setCreatedAt($now)
            ->setUpdatedAt($updated);

        $this->assertSame($token, $result);
        $this->assertSame('test.myshopify.com', $token->getShopDomain());
        $this->assertSame('shpat_123', $token->getAccessToken());
        $this->assertSame($now, $token->getCreatedAt());
        $this->assertSame($updated, $token->getUpdatedAt());
    }

    public function testSetUpdatedAtCanBeNull(): void
    {
        $token = new ShopifyOauthToken();
        $token->setUpdatedAt(null);
        $this->assertNull($token->getUpdatedAt());
    }

    public function testEntityCanBeUsedInArrayContext(): void
    {
        $token = (new ShopifyOauthToken())
            ->setShopDomain('shop123.myshopify.com')
            ->setAccessToken('abc')
            ->setCreatedAt(new \DateTimeImmutable());

        $this->assertIsArray([
            'shop' => $token->getShopDomain(),
            'token' => $token->getAccessToken(),
        ]);

        $this->assertSame('shop123.myshopify.com', $token->getShopDomain());
    }
}
