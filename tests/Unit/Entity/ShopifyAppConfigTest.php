<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Config\Enum\Protocol;
use App\Entity\ShopifyAppConfig;
use PHPUnit\Framework\TestCase;

final class ShopifyAppConfigTest extends TestCase
{
    public function testCreateEmptyForShopInitializesDefaultValues(): void
    {
        $config = ShopifyAppConfig::createEmptyForShop('test-shop.myshopify.com');

        $this->assertInstanceOf(ShopifyAppConfig::class, $config);
        $this->assertSame('test-shop.myshopify.com', $config->getShopDomain());
        $this->assertSame(Protocol::SFTP, $config->getProtocol());
        $this->assertSame('', $config->getServerUrl());
        $this->assertSame(22, $config->getPort());
        $this->assertSame('', $config->getUsername());
        $this->assertSame('', $config->getRootDirectory());
        $this->assertSame('', $config->getPrivateKeyContent());
        $this->assertSame('', $config->getKeyPassphrase());
        $this->assertSame('', $config->getFfChannelName());
        $this->assertSame('', $config->getFfApiServerUrl());
        $this->assertSame('', $config->getFfApiUsername());
        $this->assertSame('', $config->getFfApiPassword());
        $this->assertSame('', $config->getNotificationEmail());
        $this->assertInstanceOf(\DateTimeInterface::class, $config->getUpdatedAt());
    }

    public function testSettersAndGettersWorkCorrectly(): void
    {
        $config = new ShopifyAppConfig();
        $now = new \DateTime();

        $config
            ->setShopDomain('example.myshopify.com')
            ->setProtocol(Protocol::FTP)
            ->setServerUrl('ftp.example.com')
            ->setPort(21)
            ->setUsername('user')
            ->setRootDirectory('/exports')
            ->setPrivateKeyContent('private-key')
            ->setKeyPassphrase('secret')
            ->setFfChannelName('channel-1')
            ->setFfApiServerUrl('https://api.factfinder.com')
            ->setFfApiUsername('api-user')
            ->setFfApiPassword('api-pass')
            ->setNotificationEmail('admin@example.com')
            ->setUpdatedAt($now);

        $this->assertSame('example.myshopify.com', $config->getShopDomain());
        $this->assertSame(Protocol::FTP, $config->getProtocol());
        $this->assertSame('ftp.example.com', $config->getServerUrl());
        $this->assertSame(21, $config->getPort());
        $this->assertSame('user', $config->getUsername());
        $this->assertSame('/exports', $config->getRootDirectory());
        $this->assertSame('private-key', $config->getPrivateKeyContent());
        $this->assertSame('secret', $config->getKeyPassphrase());
        $this->assertSame('channel-1', $config->getFfChannelName());
        $this->assertSame('https://api.factfinder.com', $config->getFfApiServerUrl());
        $this->assertSame('api-user', $config->getFfApiUsername());
        $this->assertSame('api-pass', $config->getFfApiPassword());
        $this->assertSame('admin@example.com', $config->getNotificationEmail());
        $this->assertSame($now, $config->getUpdatedAt());
    }

    public function testChainedSettersReturnSelf(): void
    {
        $config = new ShopifyAppConfig();
        $result = $config
            ->setShopDomain('a')
            ->setProtocol(Protocol::SFTP)
            ->setServerUrl('b')
            ->setPort(22);

        $this->assertSame($config, $result, 'All setters should return $this for chaining');
    }
}
