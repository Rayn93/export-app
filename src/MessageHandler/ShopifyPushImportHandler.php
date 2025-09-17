<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ShopifyPushImportMessage;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Communication\PushImportService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShopifyPushImportHandler
{
    public function __construct(
        private ShopifyAppConfigRepository $shopifyAppConfigRepository,
        private PushImportService $pushImportService,
        private LoggerInterface $factfinderLogger,
    ) {}

    public function __invoke(ShopifyPushImportMessage $message): void
    {
        $shop = $message->getShopDomain();
        $configId = $message->getShopifyAppConfigId();
        $config = $this->shopifyAppConfigRepository->find($configId);

        if (!$config) {
            $this->factfinderLogger->error("PushImport: shopify config not found for id {$configId}", ['shop' => $shop]);

            return;
        }

        try {
            $this->pushImportService->execute($config);
            $this->factfinderLogger->info('Push import executed successfully', ['shop' => $shop]);
        } catch (\Throwable $e) {
            $this->factfinderLogger->error('Push import failed: ' . $e->getMessage(), [
                'shop' => $shop,
                'exception' => $e,
            ]);

            throw $e; // retry push import
        }
    }
}
