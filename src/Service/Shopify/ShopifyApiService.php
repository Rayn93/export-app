<?php
declare(strict_types=1);

namespace App\Service\Shopify;

use App\Repository\ShopifyOauthTokenRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ShopifyApiService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ShopifyOauthTokenRepository $shopifyTokenRepository
    ) {}

    public function getSalesChannels(string $shop): array
    {
        $query = <<<'GRAPHQL'
            query {
                publications(first: 100) {
                    edges {
                        node {
                            id
                            name
                            enabled: supportsFuturePublishing
                        }
                    }
                }
            }
        GRAPHQL;

        $data = $this->request($shop, $query);
        $salesChannels = [];

        foreach ($data['publications']['edges'] as $edge) {
            $node = $edge['node'];
            $salesChannels[preg_replace('/[^0-9]/', '', $node['id'])] = $node['name'];
        }

        return $salesChannels;
    }

    public function getLanguages(string $shop): array
    {
        $query = <<<'GRAPHQL'
            query {
                shopLocales {
                    locale
                    name
                    primary
                    published
                }
            }
        GRAPHQL;

        $data = $this->request($shop, $query);
        $languages = [];

        foreach ($data['shopLocales'] as $shopLocale) {
            $languages[$shopLocale['locale']] = $shopLocale['name'];
        }

        return $languages;
    }

    private function request(string $shop, string $query): array
    {
        $response = $this->httpClient->request('POST', "https://{$shop}/admin/api/2025-07/graphql.json", [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $this->getAccessToken($shop),
            ],
            'json' => ['query' => $query],
        ]);

        $data = $response->toArray(false);

        if (isset($data['errors'])) {
            throw new \RuntimeException('Shopify GraphQL error: ' . json_encode($data['errors']));
        }

        return $data['data'] ?? [];
    }


    private function getAccessToken(string $shop): ?string
    {
        $shopifyToken = $this->shopifyTokenRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyToken) {
            throw new \Exception('No access token found for shop: ' . $shop);
        }

        return $shopifyToken->getAccessToken();
    }
}