<?php
declare(strict_types=1);

namespace App\MessageHandler;


use App\Message\ShopifyExportProductsMessage;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\Export\FactFinderExporter;
use App\Service\Upload\UploadService;
use App\Service\Communication\PushImportService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShopifyExportProductsHandler
{
    public function __construct(
        private ShopifyAppConfigRepository $shopifyAppConfigRepository,
        private FactFinderExporter $factFinderExporter,
        private UploadService $uploadService,
        private PushImportService $pushImportService,
        private LoggerInterface $factfinderLogger
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
            // 1) wygeneruj plik CSV (metoda export powinna zwrócić ścieżkę do temp file)
            $tempFile = $this->factFinderExporter->export($shop);
            $ffChannelName = $shopifyAppConfig->getFfChannelName();
            $filename = "export.productData.$ffChannelName.csv";

            // 2) wyślij plik (UploadService powinien mieć metodę akceptującą path)
            $success = $this->uploadService->uploadForShopifyConfig($shopifyAppConfig, $tempFile, $filename);

            if ($success) {
                $this->factfinderLogger->info('Upload succeeded', ['shop' => $shop, 'filename' => $filename]);

                try {
                    $this->pushImportService->execute($shopifyAppConfig);
                } catch (\Throwable $e) {
                    $this->factfinderLogger->error('Push import failed: ' . $e->getMessage(), ['shop' => $shop]);
                }

            } else {
                $this->factfinderLogger->error('Upload failed', ['shop' => $shop]);
            }
        } catch (\Throwable $e) {
            // Loguj i -> pozwól wyjątkowi się rzucić (domyślne retry messenger) lub go złap i zaloguj (bez rethrow)
            $this->factfinderLogger->error('Export task failed: ' . $e->getMessage(), [
                'shop' => $shop,
                'exception' => $e,
            ]);

            // Rzuć ponownie, jeśli chcesz retry (domyślnie Messenger retryuje), lub nie rzutuj aby uznać za obsłużone.
//            throw $e;
        } finally {
            // usuń plik tymczasowy żeby nie zaśmiecać dysku
//            if (!empty($tempFile) && is_file($tempFile)) {
//                @unlink($tempFile);
//            }
        }
    }
}