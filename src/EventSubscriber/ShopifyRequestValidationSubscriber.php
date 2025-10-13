<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\ShopifyRequestValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ShopifyRequestValidationSubscriber implements EventSubscriberInterface
{
    private array $whitelist = [
        '/shopify/webhooks',
    ];

    public function __construct(
        private readonly ShopifyRequestValidator $validator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/shopify')) {
            return;
        }

        foreach ($this->whitelist as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return;
            }
        }

        if (!$this->validator->validateShopifyRequest($request)) {
            $event->setResponse(
                new Response('Invalid or unauthorized Shopify request', Response::HTTP_FORBIDDEN)
            );
        }
    }
}
