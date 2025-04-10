<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\ShopifyOauthTokenRepository;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ShopifyService
{
    public function __construct(
        private readonly HttpClientInterface         $client,
        private readonly ShopifyOauthTokenRepository $shopifyTokenRepository
    ) {
    }

    public function getProducts(string $shop): array
    {
        $shopifyToken = $this->shopifyTokenRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyToken) {
            throw new \Exception('No access token found for shop: ' . $shop);
        }

        $accessToken = $shopifyToken->getAccessToken();

        try {
            $response = $this->client->request('GET', "https://{$shop}/admin/api/2025-01/products.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $accessToken,
                ],
            ]);

            return $response->toArray()['products'];
        } catch (HttpExceptionInterface $e) {
            if ($e->getResponse()->getStatusCode() === 401 || $e->getResponse()->getStatusCode() === 403) {
                throw new \Exception('Access token is invalid or expired for shop: ' . $shop, 401);
            }
            throw new \Exception('Failed to fetch products: ' . $e->getMessage());
        }
    }
}