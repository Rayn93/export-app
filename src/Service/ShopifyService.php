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
    ) {}

    /**
     * Streamuje produkty partiami (GraphQL) — ~100 na zapytanie.
     * Zwraca generator, gdzie każdy yield to tablica produktów (nodes) z danej strony.
     */
    public function streamProducts(string $shop): \Generator
    {
        $shopifyToken = $this->shopifyTokenRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyToken) {
            throw new \Exception('No access token found for shop: ' . $shop);
        }

        $accessToken = $shopifyToken->getAccessToken();
        $endpoint = "https://{$shop}/admin/api/2025-07/graphql.json";
        $cursor = null;

        do {
            try {
                $response = $this->client->request('POST', $endpoint, [
                    'headers' => [
                        'X-Shopify-Access-Token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'query'     => $this->getGQL(),
                        'variables' => ['first' => 100, 'after' => $cursor],
                    ],
                ]);

                $payload = $response->toArray();
            } catch (HttpExceptionInterface $e) {
                if (in_array($e->getResponse()->getStatusCode(), [401, 403], true)) {
                    throw new \Exception('Access token is invalid or expired for shop: ' . $shop, 401);
                }
                throw new \Exception('Failed to fetch products: ' . $e->getMessage());
            }

            $productsConnection = $payload['data']['products'] ?? null;

            if (!$productsConnection) {
                break;
            }

            $edges = $productsConnection['edges'] ?? [];
            $products = array_map(static fn(array $edge) => $edge['node'], $edges);

            if ($products) {
                yield $products;
            }

            $pageInfo = $productsConnection['pageInfo'] ?? [];
            $hasNext  = (bool)($pageInfo['hasNextPage'] ?? false);
            $cursor   = $pageInfo['endCursor'] ?? null;
        } while ($hasNext && $cursor);
    }

    public function getGQL() : string
    {
        return <<<'GQL'
query Products($first: Int!, $after: String) {
  products(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        id
        legacyResourceId
        title
        vendor
        handle
        descriptionHtml
        onlineStoreUrl
        category { id fullName }
        images(first: 1) { edges { node { url } } }
        variants(first: 100) {
          edges {
            node {
              id
              legacyResourceId
              title
              price 
              selectedOptions { name value }
            }
          }
        }
      }
    }
  }
}
GQL;
    }
}