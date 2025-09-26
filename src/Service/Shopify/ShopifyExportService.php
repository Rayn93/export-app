<?php
declare(strict_types=1);

namespace App\Service\Shopify;

use App\Repository\ShopifyOauthTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ShopifyExportService
{
    public function __construct(
        private HttpClientInterface         $client,
        private ShopifyOauthTokenRepository $shopifyTokenRepository,
        private LoggerInterface             $factfinderLogger
    ) {}

    /**
     * Streamuje produkty partiami (GraphQL) — ~100 na zapytanie.
     * Zwraca generator, gdzie każdy yield to tablica produktów (nodes) z danej strony.
     */
    public function streamProducts(string $shop, string $salesChannel, string $locale): \Generator
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
                        'query'     => $this->getGQL($salesChannel),
                        'variables' => ['first' => 250, 'after' => $cursor, 'locale' => $locale],
                    ],
                ]);

                $payload = $response->toArray();

            } catch (HttpExceptionInterface $e) {
                if (in_array($e->getResponse()->getStatusCode(), [401, 403], true)) {
                    throw new \Exception('Access token is invalid or expired for shop: ' . $shop, 401);
                }

                throw new \Exception('Failed to fetch products: ' . $e->getMessage());
            }

            if (!empty($payload['errors'])) {
                $this->factfinderLogger->error('GraphQL errors', ['error' => $payload['errors'][0]['message'] ?? 'unknown']);

                throw new \Exception('GraphQL errors: ' . json_encode($payload['errors']));
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

    public function getGQL($publicationId) : string
    {
        $query = sprintf('
query Products($first: Int!, $after: String, $locale: String!) {
  products(first: $first, after: $after, query: "status:active, publication_ids:%s") {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        id
        legacyResourceId
        translations(locale:$locale){key value}
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
              translations(locale: $locale) { key value }
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
}', $publicationId);

        return $query;
    }
}