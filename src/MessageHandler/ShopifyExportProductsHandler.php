<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ShopifyExportProductsMessage;
use App\Message\ShopifyUploadFileMessage;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Export\FactFinderExporter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ShopifyExportProductsHandler
{
    public function __construct(
        private ShopifyAppConfigRepository $shopifyAppConfigRepository,
        private FactFinderExporter $factFinderExporter,
        private MessageBusInterface $bus,
        private LoggerInterface $factfinderLogger,
    ) {
    }

    public function __invoke(ShopifyExportProductsMessage $message): void
    {
        $shop = $message->getShopDomain();
        $configId = $message->getShopifyAppConfigId();
        $shopifyAppConfig = $this->shopifyAppConfigRepository->find($configId);

        if (!$shopifyAppConfig) {
            $this->factfinderLogger->error("Export: shopify config not found for id {$configId}", ['shop' => $shop]);

            return;
        }

        try {
            $tempFile = $this->factFinderExporter->export($shop, $message->getSalesChannelId(), $message->getLocale());

            if (filesize($tempFile) < 1000) {
                $this->factfinderLogger->error(
                    'Exported file too small, problem with product export',
                    ['shop' => $shop, 'filesize' => filesize($tempFile)]
                );

                throw new RuntimeException('Exported file too small, problem with product export');
            }

            $this->bus->dispatch(
                new ShopifyUploadFileMessage($shop, $configId, $tempFile, $message->getMailForFailureNotification())
            );
            $this->factfinderLogger->info('Shopify export file created successfully', ['shop' => $shop]);
        } catch (\Throwable $e) {
            $this->factfinderLogger->error('Export file cannot be created. Error: ' . $e->getMessage(), [
                'shop' => $shop,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
