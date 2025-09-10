<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\ShopifyOauthToken;
use App\Repository\ShopifyOauthTokenRepository;
use League\OAuth2\Client\Provider\GenericProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{

    public function __construct(private readonly ShopifyOauthTokenRepository $shopifyTokenRepository)
    {
    }

    #[Route('/shopify/auth/install', name: 'shopify_install')]
    public function install(Request $request, SessionInterface $session): Response
    {
        $shop = $request->query->get('shop');

        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        $provider = new GenericProvider([
            'clientId' => $this->getParameter('shopify_client_id'),
            'clientSecret' => $this->getParameter('shopify_client_secret'),
            'redirectUri' => $this->getParameter('shopify_redirect_uri'),
            'urlAuthorize' => "https://{$shop}/admin/oauth/authorize",
            'urlAccessToken' => "https://{$shop}/admin/oauth/access_token",
            'urlResourceOwnerDetails' => '',
            'scopes' => 'read_products',
        ]);

        $authUrl = $provider->getAuthorizationUrl();
        $session->set('oauth2_state', $provider->getState());

        return $this->redirect($authUrl);
    }

    #[Route('/shopify/auth/callback', name: 'shopify_callback')]
    public function callback(Request $request, SessionInterface $session): Response
    {
        $shop = $request->query->get('shop');
        $code = $request->query->get('code');
        $state = $request->query->get('state');

        if ($state !== $session->get('oauth2_state')) {
            return new Response('Invalid OAuth state', 400);
        }

        $provider = new GenericProvider([
            'clientId' => $this->getParameter('shopify_client_id'),
            'clientSecret' => $this->getParameter('shopify_client_secret'),
            'redirectUri' => $this->getParameter('shopify_redirect_uri'),
            'urlAuthorize' => "https://{$shop}/admin/oauth/authorize",
            'urlAccessToken' => "https://{$shop}/admin/oauth/access_token",
            'urlResourceOwnerDetails' => '',
            'scopes' => 'read_products',
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

        return $this->redirectToRoute('app_shopify_config', $request->query->all());
    }
}