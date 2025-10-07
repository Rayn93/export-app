<?php
declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ShopifyRequestValidationSubscriber;
use App\Service\ShopifyRequestValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ShopifyRequestValidationSubscriberTest extends TestCase
{
    private ShopifyRequestValidator $validator;
    private ShopifyRequestValidationSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(ShopifyRequestValidator::class);
        $this->subscriber = new ShopifyRequestValidationSubscriber($this->validator);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testDoesNothingForNonShopifyPaths(): void
    {
        $request = new Request([], [], ['_route' => 'homepage']);
        $request->server->set('REQUEST_URI', '/not-shopify');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->validator->expects($this->never())
            ->method('validateShopifyRequest');

        $this->subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testDoesNothingForWhitelistedPath(): void
    {
        $request = Request::create('/shopify/webhooks', 'POST');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->validator->expects($this->never())
            ->method('validateShopifyRequest');

        $this->subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testSetsForbiddenResponseWhenValidationFails(): void
    {
        $request = Request::create('/shopify/auth', 'GET');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->validator->expects($this->once())
            ->method('validateShopifyRequest')
            ->with($request)
            ->willReturn(false);

        $this->subscriber->onKernelRequest($event);
        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('Invalid or unauthorized Shopify request', $response->getContent());
    }

    public function testDoesNothingWhenValidationSucceeds(): void
    {
        $request = Request::create('/shopify/auth', 'GET');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->validator->expects($this->once())
            ->method('validateShopifyRequest')
            ->willReturn(true);

        $this->subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testGetSubscribedEventsReturnsExpectedArray(): void
    {
        $expected = [
            \Symfony\Component\HttpKernel\KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
        $this->assertSame($expected, ShopifyRequestValidationSubscriber::getSubscribedEvents());
    }
}
