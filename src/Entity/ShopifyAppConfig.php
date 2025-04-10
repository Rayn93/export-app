<?php
declare(strict_types=1);

namespace App\Entity;

use App\Config\Enum\Protocol;
use App\Repository\ShopifyAppConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopifyAppConfigRepository::class)]
#[ORM\Table(name: 'shopify_app_config')]
class ShopifyAppConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $shopDomain = null;

    #[ORM\Column(type: 'string', enumType: Protocol::class)]
    private ?Protocol $protocol = null;

    #[ORM\Column(length: 255)]
    private ?string $serverUrl = null;

    #[ORM\Column]
    private ?int $port = null;

    #[ORM\Column(length: 255)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $rootDirectory = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $privateKeyContent = null;

    #[ORM\Column(length: 255)]
    private ?string $keyPassphrase = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    public static function createEmptyForShop(string $shopDomain): self
    {
        $config = new self();
        $config->setShopDomain($shopDomain);
        $config->setProtocol(Protocol::SFTP);
        $config->setServerUrl('');
        $config->setPort(22);
        $config->setUsername('');
        $config->setRootDirectory('');
        $config->setPrivateKeyContent('');
        $config->setKeyPassphrase('');
        $config->setUpdatedAt(new \DateTime());

        return $config;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShopDomain(): ?string
    {
        return $this->shopDomain;
    }

    public function setShopDomain(string $shopDomain): static
    {
        $this->shopDomain = $shopDomain;

        return $this;
    }

    public function getProtocol(): ?Protocol
    {
        return $this->protocol;
    }

    public function setProtocol(Protocol $protocol): static
    {
        $this->protocol = $protocol;

        return $this;
    }

    public function getServerUrl(): ?string
    {
        return $this->serverUrl;
    }

    public function setServerUrl(string $serverUrl): static
    {
        $this->serverUrl = $serverUrl;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getRootDirectory(): ?string
    {
        return $this->rootDirectory;
    }

    public function setRootDirectory(string $rootDirectory): static
    {
        $this->rootDirectory = $rootDirectory;

        return $this;
    }

    public function getPrivateKeyContent(): ?string
    {
        return $this->privateKeyContent;
    }

    public function setPrivateKeyContent(string $privateKeyContent): static
    {
        $this->privateKeyContent = $privateKeyContent;

        return $this;
    }

    public function getKeyPassphrase(): ?string
    {
        return $this->keyPassphrase;
    }

    public function setKeyPassphrase(string $keyPassphrase): static
    {
        $this->keyPassphrase = $keyPassphrase;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}