<?php
declare(strict_types=1);

namespace App\Service\Shopify;

use App\Repository\ShopifyOauthTokenRepository;
use App\Repository\ShopifyAppConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

readonly class ShopifyUninstallService
{
    public function __construct(
        private EntityManagerInterface      $em,
        private ShopifyOauthTokenRepository $tokenRepository,
        private ShopifyAppConfigRepository  $configRepository,
        private LoggerInterface             $factfinderLogger,
    ) {}

    public function removeDataForShop(string $shopDomain): void
    {
        $this->factfinderLogger->info('Starting uninstall cleanup', ['shop' => $shopDomain]);
        $this->em->beginTransaction();

        try {
            $token = $this->tokenRepository->findOneBy(['shopDomain' => $shopDomain]);

            if ($token) {
                $this->em->remove($token);
                $this->factfinderLogger->info('Removed ShopifyOauthToken', ['shop' => $shopDomain]);
            } else {
                $this->factfinderLogger->info('No ShopifyOauthToken found', ['shop' => $shopDomain]);
            }

            $config = $this->configRepository->findOneBy(['shopDomain' => $shopDomain]);

            if ($config) {
                $this->em->remove($config);
                $this->factfinderLogger->info('Removed ShopifyAppConfig', ['shop' => $shopDomain]);
            } else {
                $this->factfinderLogger->info('No ShopifyAppConfig found', ['shop' => $shopDomain]);
            }

            $this->em->flush();
            $this->em->commit();

            $this->factfinderLogger->info('Uninstall cleanup finished', ['shop' => $shopDomain]);
        } catch (\Throwable $e) {
            $this->factfinderLogger->error('Uninstall cleanup failed, rolling back', [
                'shop' => $shopDomain,
                'exception' => $e,
            ]);

            // rollback transaction to avoid partial deletes in DB
            try {
                $this->em->rollback();
            } catch (\Throwable $inner) {
                $this->factfinderLogger->error('Rollback failed', ['exception' => $inner]);
            }
            throw $e;
        }
    }
}
