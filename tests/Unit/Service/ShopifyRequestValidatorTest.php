<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ShopifyRequestValidator;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class ShopifyRequestValidatorTest extends TestCase
{
    private string $clientSecret = 'test_secret';
    private string $clientId = 'client_123';
    private \PHPUnit\Framework\MockObject\MockObject&LoggerInterface $logger;
    private ShopifyRequestValidator $validator;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->validator = new ShopifyRequestValidator(
            $this->clientSecret,
            $this->clientId,
            $this->logger
        );
    }

    public function testValidateHmacRequestReturnsFalseWhenShopMissing(): void
    {
        $request = new Request(['hmac' => 'abc']);
        $this->logger->expects($this->once())->method('error')->with('Shop parameter is missing');
        $result = $this->validator->validateHmacRequest($request);
        $this->assertFalse($result);
    }

    public function testValidateHmacRequestReturnsFalseWhenHmacInvalid(): void
    {
        $request = new Request(['shop' => 'test.myshopify.com', 'hmac' => 'invalid']);
        $this->logger->expects($this->once())->method('error')->with(
            'HMAC verification failed',
            $this->arrayHasKey('query')
        );
        $result = $this->validator->validateHmacRequest($request);
        $this->assertFalse($result);
    }

    public function testValidateHmacRequestReturnsTrueWhenValid(): void
    {
        $initial = [
            'shop' => 'validshop.myshopify.com',
            'param' => 'value'
        ];

        $request = new Request($initial);
        $paramsForHmac = $request->query->all();
        ksort($paramsForHmac);
        $queryString = urldecode(http_build_query($paramsForHmac));
        $hmac = hash_hmac('sha256', $queryString, $this->clientSecret);
        $request->query->set('hmac', $hmac);
        $this->logger->expects($this->never())->method('error');
        $result = $this->validator->validateHmacRequest($request);
        $this->assertTrue($result);
    }

    public function testValidateShopifyRequestUsesBearerTokenWhenPresent(): void
    {
        $payload = [
            'aud' => $this->clientId,
            'dest' => 'https://test.myshopify.com',
            'exp' => time() + 3600,
        ];
        $token = JWT::encode($payload, $this->clientSecret, 'HS256');

        $request = new Request(['shop' => 'test.myshopify.com']);
        $request->headers->set('Authorization', "Bearer {$token}");
        $this->logger->expects($this->atLeastOnce())->method('info');
        $result = $this->validator->validateShopifyRequest($request);
        $this->assertTrue($result);
    }

    public function testVerifySessionTokenReturnsFalseWhenAudienceMismatch(): void
    {
        $payload = [
            'aud' => 'wrong_client',
            'dest' => 'https://test.myshopify.com',
            'exp' => time() + 3600,
        ];
        $token = JWT::encode($payload, $this->clientSecret, 'HS256');
        $this->logger->expects($this->once())->method('error')->with(
            'Audience mismatch',
            $this->arrayHasKey('expected')
        );
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('verifySessionToken');
        $method->setAccessible(true);
        $result = $method->invoke($this->validator, $token, 'test.myshopify.com');
        $this->assertFalse($result);
    }

    public function testVerifySessionTokenFailsWhenDestMismatch(): void
    {
        $payload = [
            'aud' => $this->clientId,
            'dest' => 'https://other-shop.myshopify.com',
            'exp' => time() + 3600,
        ];
        $token = JWT::encode($payload, $this->clientSecret, 'HS256');
        $this->logger->expects($this->once())->method('error')->with(
            'Destination mismatch',
            $this->arrayHasKey('expected')
        );
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('verifySessionToken');
        $method->setAccessible(true);
        $result = $method->invoke($this->validator, $token, 'test.myshopify.com');
        $this->assertFalse($result);
    }

    public function testVerifySessionTokenFailsOnInvalidJwt(): void
    {
        $this->logger->expects($this->once())->method('error')->with(
            'Session token verification failed',
            $this->arrayHasKey('error')
        );
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('verifySessionToken');
        $method->setAccessible(true);
        $result = $method->invoke($this->validator, 'not.a.jwt', 'test.myshopify.com');
        $this->assertFalse($result);
    }
}
