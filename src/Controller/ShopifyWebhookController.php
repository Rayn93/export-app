<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Shopify\ShopifyUninstallService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ShopifyWebhookController extends AbstractController
{
    public function __construct(
        private readonly ShopifyUninstallService $uninstallService,
        private readonly LoggerInterface $factfinderLogger,
        private readonly string $clientSecret
    ) {}

    #[Route('/shopify/webhooks/app/uninstalled', name: 'shopify_webhook_app_uninstalled', methods: ['POST'])]
    public function handleUninstall(Request $request): Response
    {
        $rawBody = $request->getContent();
        $hmac = $request->headers->get('X-Shopify-Hmac-Sha256');
        $shop = $request->headers->get('X-Shopify-Shop-Domain');

        if (!$hmac || !$shop) {
            $this->factfinderLogger->warning('Shopify uninstall webhook missing required headers', [
                'headers' => $request->headers->all()
            ]);

            return new Response('Missing headers', Response::HTTP_BAD_REQUEST);
        }

        // verify HMAC
        $calculated = base64_encode(hash_hmac('sha256', $rawBody, $this->clientSecret, true));

        if (!hash_equals($calculated, $hmac)) {
            $this->factfinderLogger->warning('Shopify uninstall webhook HMAC verification failed', ['shop' => $shop]);

            return new Response('Invalid HMAC', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->uninstallService->removeDataForShop($shop);
            $this->factfinderLogger->info('Shopify uninstall processed successfully', ['shop' => $shop]);

            return new Response('OK', Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->factfinderLogger->error('Error while processing uninstall webhook', [
                'shop' => $shop,
                'exception' => $e,
            ]);

            return new Response('OK', Response::HTTP_OK);
        }
    }
}
