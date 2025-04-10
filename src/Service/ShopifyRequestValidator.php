<?php
declare(strict_types=1);

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class ShopifyRequestValidator
{
    public function __construct(
        private string          $clientSecret,
        private string          $clientId,
        private LoggerInterface $logger
    ) {
    }

    public function validateShopifyRequest(Request $request): bool
    {
        $shop = $request->query->get('shop');
        $hmac = $request->query->get('hmac');
        $query = $request->query->all();

        if (!$this->verifyHmac($query, $hmac)) {
            $this->logger->error('HMAC verification failed', ['query' => $query, 'hmac' => $hmac]);

            return false;
        }

        if (!$shop) {
            $this->logger->error('Shop parameter is missing');
            return false;
        }

        $token = $request->query->get('id_token');

        if (!$token) {
            $this->logger->error('id_token parameter is missing');
            return false;
        }

        return $this->verifySessionToken($token, $shop);
    }

    private function verifyHmac(array $query, ?string $hmac): bool
    {
        if (!$hmac) {
            return false;
        }

        $params = $query;
        unset($params['hmac']);
        ksort($params);
        $queryString = http_build_query($params);
        $calculatedHmac = hash_hmac('sha256', $queryString, $this->clientSecret);

        return hash_equals($hmac, $calculatedHmac);
    }

    private function verifySessionToken(string $token, string $shop): bool
    {
        try {
            $this->logger->info('Verifying session token', ['shop' => $shop]);
            $decodedToken = JWT::decode($token, new Key($this->clientSecret, 'HS256'));
            $this->logger->info('Token decoded', ['decodedToken' => (array) $decodedToken]);

            if ($decodedToken->aud !== $this->clientId) {
                $this->logger->error('Audience mismatch', [
                    'expected' => $this->clientId,
                    'actual' => $decodedToken->aud,
                ]);

                return false;
            }

            if ($decodedToken->dest !== "https://{$shop}") {
                $this->logger->error('Destination mismatch', [
                    'expected' => "https://{$shop}",
                    'actual' => $decodedToken->dest,
                ]);

                return false;
            }

            if ($decodedToken->exp < time()) {
                $this->logger->error('Token expired', [
                    'exp' => $decodedToken->exp,
                    'currentTime' => time(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Session token verification failed', [
                'error' => $e->getMessage(),
                'token' => $token,
                'shop' => $shop,
            ]);

            return false;
        }
    }
}