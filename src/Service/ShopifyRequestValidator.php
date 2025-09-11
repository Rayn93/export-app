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
        private LoggerInterface $factfinderLogger,
    ) {
    }

    /**
     * Automatycznie wybiera tryb walidacji:
     * - HMAC → dla callbacków i config page
     * - JWT  → dla requestów z App Bridge (iframe)
     */
    public function validateShopifyRequest(Request $request): bool
    {
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $this->verifySessionToken($token, $request->query->get('shop'));
        }

        // 2️⃣ W przeciwnym razie → HMAC
        return $this->validateHmacRequest($request);
    }

    /**
     * Walidacja dla OAuth callback i config page (HMAC).
     */
    public function validateHmacRequest(Request $request): bool
    {
        $shop = $request->query->get('shop');
        $hmac = $request->query->get('hmac');
        $query = $request->query->all();

        if (!$shop) {
            $this->factfinderLogger->error('Shop parameter is missing');
            return false;
        }

        if (!$this->verifyHmac($query, $hmac)) {
            $this->factfinderLogger->error('HMAC verification failed', [
                'query' => $query,
                'hmac'  => $hmac,
            ]);
            return false;
        }

        return true;
    }

    private function verifyHmac(array $query, ?string $hmac): bool
    {
        if (!$hmac) {
            return false;
        }

        $params = $query;
        unset($params['hmac']);

        ksort($params);

        // Shopify liczy HMAC na URLEncoded params
        $queryString = urldecode(http_build_query($params));
        $calculatedHmac = hash_hmac('sha256', $queryString, $this->clientSecret);

        return hash_equals($hmac, $calculatedHmac);
    }

    /**
     * Walidacja dla App Bridge (JWT session token).
     */
    private function verifySessionToken(string $token, ?string $shop): bool
    {
        try {
            $this->factfinderLogger->info('Verifying session token', ['shop' => $shop]);
            $decodedToken = JWT::decode($token, new Key($this->clientSecret, 'HS256'));
            $this->factfinderLogger->info('Token decoded', ['decodedToken' => (array) $decodedToken]);

            if ($decodedToken->aud !== $this->clientId) {
                $this->factfinderLogger->error('Audience mismatch', [
                    'expected' => $this->clientId,
                    'actual' => $decodedToken->aud,
                ]);
                return false;
            }

            if ($shop && $decodedToken->dest !== "https://{$shop}") {
                $this->factfinderLogger->error('Destination mismatch', [
                    'expected' => "https://{$shop}",
                    'actual'   => $decodedToken->dest,
                ]);
                return false;
            }

            if ($decodedToken->exp < time()) {
                $this->factfinderLogger->error('Token expired', [
                    'exp' => $decodedToken->exp,
                    'currentTime' => time(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->factfinderLogger->error('Session token verification failed', [
                'error' => $e->getMessage(),
                'token' => $token,
                'shop'  => $shop,
            ]);
            return false;
        }
    }
}
