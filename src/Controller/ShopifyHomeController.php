<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ShopifyOauthTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ShopifyHomeController extends AbstractController
{
    public function __construct(private readonly ShopifyOauthTokenRepository $shopifyOauthTokenRepository, private readonly LoggerInterface $factfinderLogger)
    {
    }

    #[Route('/shopify', name: 'shopify_home')]
    public function index(Request $request): Response
    {
        $shopDomain = $request->query->get('shop');

        if (!$shopDomain) {
            return new Response('Missing shop parameter', 400);
        }

        $tokenExist = (bool) $this->shopifyOauthTokenRepository->count(['shopDomain' => $shopDomain]);

        if ($tokenExist) {
            return $this->redirectToRoute('shopify_config', $request->query->all());
        } else {
            return $this->redirectToRoute('shopify_install', $request->query->all());
        }

    }
}
