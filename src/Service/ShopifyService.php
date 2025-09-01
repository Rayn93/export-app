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

//    public function getProducts(string $shop): array
//    {
//        $shopifyToken = $this->shopifyTokenRepository->findOneBy(['shopDomain' => $shop]);
//
//        if (!$shopifyToken) {
//            throw new \Exception('No access token found for shop: ' . $shop);
//        }
//
//        $accessToken = $shopifyToken->getAccessToken();
//
//        try {
//            $response = $this->client->request('GET', "https://{$shop}/admin/api/2025-01/products.json", [
//                'headers' => [
//                    'X-Shopify-Access-Token' => $accessToken,
//                ],
//            ]);
//
//            return $response->toArray()['products'];
//        } catch (HttpExceptionInterface $e) {
//            if ($e->getResponse()->getStatusCode() === 401 || $e->getResponse()->getStatusCode() === 403) {
//                throw new \Exception('Access token is invalid or expired for shop: ' . $shop, 401);
//            }
//            throw new \Exception('Failed to fetch products: ' . $e->getMessage());
//        }
//    }

    public function getProducts(string $shop): array
    {
        $shopifyToken = $this->shopifyTokenRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyToken) {
            throw new \Exception('No access token found for shop: ' . $shop);
        }

        $accessToken = $shopifyToken->getAccessToken();
        $allProducts = [];

        $endpoint = "https://{$shop}/admin/api/2025-01/products.json?limit=250";
        $headers = [
            'X-Shopify-Access-Token' => $accessToken,
        ];

        try {
            while ($endpoint) {
                $response = $this->client->request('GET', $endpoint, [
                    'headers' => $headers,
                ]);

                $data = $response->toArray();
                $allProducts = array_merge($allProducts, $data['products'] ?? []);

                // Obsługa nagłówka "Link" do kolejnej strony
                $linkHeader = $response->getHeaders(false)['link'][0] ?? null;
                $endpoint = $this->getNextPageUrl($linkHeader);
            }
        } catch (HttpExceptionInterface $e) {
            if ($e->getResponse()->getStatusCode() === 401 || $e->getResponse()->getStatusCode() === 403) {
                throw new \Exception('Access token is invalid or expired for shop: ' . $shop, 401);
            }
            throw new \Exception('Failed to fetch products: ' . $e->getMessage());
        }

        return $allProducts;
    }

    private function getNextPageUrl(?string $linkHeader): ?string
    {
        if (!$linkHeader) {
            return null;
        }

        // przykładowy nagłówek:
        // <https://myshop.myshopify.com/admin/api/2025-01/products.json?page_info=abcd&limit=250>; rel="next"
        foreach (explode(',', $linkHeader) as $part) {
            if (str_contains($part, 'rel="next"')) {
                if (preg_match('/<([^>]+)>/', $part, $matches)) {
                    return $matches[1];
                }
            }
        }

        return null;
    }
}