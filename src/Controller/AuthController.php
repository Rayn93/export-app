<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ShopifyOauthToken;
use App\Repository\ShopifyOauthTokenRepository;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly ShopifyOauthTokenRepository $shopifyTokenRepository,
        private readonly LoggerInterface $factfinderLogger,
    ) {
    }

    #[Route('/shopify/auth/install', name: 'shopify_install')]
    public function install(Request $request, SessionInterface $session): Response
    {
        $shop = $request->query->get('shop');

        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        $this->factfinderLogger->info('Starting OAuth2 flow', ['shop' => $shop]);
        $provider = new GenericProvider([
            'clientId' => $this->getParameter('shopify_client_id'),
            'clientSecret' => $this->getParameter('shopify_client_secret'),
            'redirectUri' => $this->getParameter('shopify_redirect_uri'),
            'urlAuthorize' => "https://{$shop}/admin/oauth/authorize",
            'urlAccessToken' => "https://{$shop}/admin/oauth/access_token",
            'urlResourceOwnerDetails' => '',
            'scopes' => 'read_locales,read_products,read_publications',
        ]);

        $authUrl = $provider->getAuthorizationUrl();
        $session->set('oauth2_state', $provider->getState());
        $this->factfinderLogger->info("Oauth AuthUrl: $authUrl", ['shop' => $shop]);

        return $this->redirect($authUrl);
    }

    #[Route('/shopify/auth/callback', name: 'shopify_callback')]
    public function callback(Request $request, SessionInterface $session): Response
    {
        $shop = $request->query->get('shop');
        $code = $request->query->get('code');
        $state = $request->query->get('state');

        if ($state !== $session->get('oauth2_state')) {
            $this->factfinderLogger->error('Invalid OAuth state', ['shop' => $shop]);

            return new Response('Invalid OAuth state', 400);
        }

        $this->factfinderLogger->info('Starting OAuth2 callback flow', ['shop' => $shop]);
        $provider = new GenericProvider([
            'clientId' => $this->getParameter('shopify_client_id'),
            'clientSecret' => $this->getParameter('shopify_client_secret'),
            'redirectUri' => $this->getParameter('shopify_redirect_uri'),
            'urlAuthorize' => "https://{$shop}/admin/oauth/authorize",
            'urlAccessToken' => "https://{$shop}/admin/oauth/access_token",
            'urlResourceOwnerDetails' => '',
            'scopes' => 'read_locales,read_products,read_publications',
        ]);

        $accessToken = $provider->getAccessToken('authorization_code', ['code' => $code]);
        $shopifyToken = $this->shopifyTokenRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyToken) {
            $shopifyToken = new ShopifyOauthToken();
            $shopifyToken->setShopDomain($shop);
            $shopifyToken->setCreatedAt(new \DateTime());
        }

        $shopifyToken->setAccessToken($accessToken->getToken());
        $shopifyToken->setUpdatedAt(new \DateTime());
        $this->shopifyTokenRepository->save($shopifyToken, true);
        $this->factfinderLogger->info('OAuth2 callback flow success. Redirect to config', ['shop' => $shop]);

        return $this->redirectToRoute('shopify_config', $request->query->all());
    }
}
