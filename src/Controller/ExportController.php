<?php
declare(strict_types=1);

namespace App\Controller;

use App\Message\ShopifyExportProductsMessage;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\ShopifyRequestValidator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class ExportController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $factfinderLogger) {
    }

    #[Route('/shopify/export/products/', name: 'shopify_export_products', methods: ['POST'])]
    public function exportProducts(
        Request $request,
        ShopifyAppConfigRepository $shopifyAppConfigRepository,
        ShopifyRequestValidator $validator,
        MessageBusInterface $bus,
    ): Response {
        if (!$validator->validateShopifyRequest($request)) {
            return new Response('Unauthorized', 401);
        }

        $shop = $request->query->get('shop');
        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        $shopifyAppConfig = $shopifyAppConfigRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyAppConfig) {
            $this->addFlash('error', 'Configuration not found for this shop.');
            $this->factfinderLogger->error("Executed export without configuration for: $shop.");

            return $this->redirectToRoute('app_shopify_config', $request->query->all());
        }

        $message = new ShopifyExportProductsMessage($shop, $shopifyAppConfig->getId(), $shopifyAppConfig->getNotificationEmail());
        $bus->dispatch($message);
        $this->addFlash('success', 'Export queued. You will be notified when finished.');
        $this->factfinderLogger->info('Export queued', ['shop' => $shop, 'configId' => $shopifyAppConfig->getId()]);

        return $this->redirectToRoute('app_shopify_config', $request->query->all());
    }
}