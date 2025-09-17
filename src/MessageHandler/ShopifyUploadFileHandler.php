<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ShopifyUploadFileMessage;
use App\Message\ShopifyPushImportMessage;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Upload\UploadService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ShopifyUploadFileHandler
{
    public function __construct(
        private ShopifyAppConfigRepository $shopifyAppConfigRepository,
        private UploadService $uploadService,
        private MessageBusInterface $bus,
        private LoggerInterface $factfinderLogger,
    ) {}

    public function __invoke(ShopifyUploadFileMessage $message): void
    {
        $shop = $message->getShopDomain();
        $configId = $message->getShopifyAppConfigId();
        $config = $this->shopifyAppConfigRepository->find($configId);

        if (!$config) {
            $this->factfinderLogger->error("Upload: shopify config not found for id {$configId}", ['shop' => $shop]);

            return;
        }

        try {
            $ffChannelName = $config->getFfChannelName();
            $filename = "export.productData.$ffChannelName.csv";
            $success = $this->uploadService->uploadForShopifyConfig($config, $message->getFilePath(), $filename);

            if ($success) {
                $this->factfinderLogger->info('Upload file succeeded', [
                    'shop' => $shop,
                    'filename' => $filename,
                ]);
                $this->bus->dispatch(new ShopifyPushImportMessage($shop, $configId));

            } else {
                $this->factfinderLogger->error('Upload file failed', ['shop' => $shop]);

                throw new RuntimeException('Upload file failed. Trying again.');
            }
        } catch (\Throwable $e) {
            $this->factfinderLogger->error('Upload task failed: ' . $e->getMessage(), [
                'shop' => $shop,
                'exception' => $e,
            ]);

            throw $e; // retry upload
        }
    }
}
