<?php

namespace App\Controller;

use App\Config\Enum\Protocol;
use App\Entity\ShopifyAppConfig;
use App\Repository\ShopifyAppConfigRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ShopifyConfigController extends AbstractController
{
    #[Route('/shopify/config', name: 'app_shopify_config')]
    public function index(Request $request, ShopifyAppConfigRepository $appConfigRepository): Response
    {
        $shop = $request->query->get('shop');
        $host = $request->query->get('host');

        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        // Pobierz ustawienia dla sklepu
        $shopifyAppConfig = $appConfigRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyAppConfig) {
            $shopifyAppConfig = ShopifyAppConfig::createEmptyForShop($shop);
        }

        // Obsługa formularza
        if ($request->isMethod('POST')) {
            $shopifyAppConfig->setProtocol(Protocol::from($request->request->get('protocol')));
            $shopifyAppConfig->setServerUrl($request->request->get('server_url'));
            $shopifyAppConfig->setPort((int) $request->request->get('port'));
            $shopifyAppConfig->setUsername($request->request->get('username'));
            $shopifyAppConfig->setRootDirectory($request->request->get('root_directory'));
            $shopifyAppConfig->setPrivateKeyContent($request->request->get('private_key_content'));
            $shopifyAppConfig->setKeyPassphrase($request->request->get('key_passphrase'));
            $shopifyAppConfig->setUpdatedAt(new \DateTime());
            $appConfigRepository->save($shopifyAppConfig, true);
            $this->addFlash('success', 'Configuration saved successfully!');

            return $this->redirectToRoute('app_shopify_config', ['shop' => $shop, 'host' => $host]);
        }

        return $this->render('shopify_config/index.html.twig', [
            'shop' => $shop,
            'host' => $host,
            'shopify_client_id' => $this->getParameter('shopify_client_id'),
            'config' => $shopifyAppConfig,
        ]);
    }
}
