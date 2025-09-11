<?php
declare(strict_types=1);

namespace App\Controller;

use App\Config\Enum\Protocol;
use App\Entity\ShopifyAppConfig;
use App\Repository\ShopifyAppConfigRepository;
use App\Service\ShopifyRequestValidator;
use App\Service\Utils\PasswordEncryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ShopifyConfigController extends AbstractController
{
    #[Route('/shopify/config', name: 'app_shopify_config')]
    public function index(
        Request $request,
        ShopifyAppConfigRepository $appConfigRepository,
        ShopifyRequestValidator $validator,
        PasswordEncryptor $passwordEncryptor
    ): Response
    {
        if (!$validator->validateShopifyRequest($request)) {
            return new Response('Unauthorized', 401);
        }

        $shop = $request->query->get('shop');
        $host = $request->query->get('host');

        if (!$shop) {
            return new Response('Missing shop parameter', 400);
        }

        $shopifyAppConfig = $appConfigRepository->findOneBy(['shopDomain' => $shop]);

        if (!$shopifyAppConfig) {
            $shopifyAppConfig = ShopifyAppConfig::createEmptyForShop($shop);
        }

        if ($request->isMethod('POST')) {
            $shopifyAppConfig->setProtocol(Protocol::from($request->request->get('protocol')));
            $shopifyAppConfig->setServerUrl($request->request->get('server_url'));
            $shopifyAppConfig->setPort((int) $request->request->get('port'));
            $shopifyAppConfig->setUsername($request->request->get('username'));
            $shopifyAppConfig->setRootDirectory($request->request->get('root_directory'));
            $shopifyAppConfig->setPrivateKeyContent($request->request->get('private_key_content'));
            $shopifyAppConfig->setKeyPassphrase($passwordEncryptor->encrypt($request->request->get('key_passphrase')));
            $shopifyAppConfig->setFfChannelName($request->request->get('ff_channel_name'));
            $shopifyAppConfig->setFfApiServerUrl($request->request->get('ff_api_server_url'));
            $shopifyAppConfig->setFfApiUsername($request->request->get('ff_api_username'));
            $shopifyAppConfig->setFfApiPassword(!empty($request->request->get('ff_api_password')) ? $passwordEncryptor->encrypt($request->request->get('ff_api_password')) : '');
            $shopifyAppConfig->setUpdatedAt(new \DateTime());
            $appConfigRepository->save($shopifyAppConfig, true);
            $this->addFlash('success', 'Configuration saved successfully!');

            return $this->redirectToRoute('app_shopify_config', $request->query->all());
        }

        return $this->render('shopify_config/index.html.twig', [
            'shop' => $shop,
            'host' => $host,
            'shopify_client_id' => $this->getParameter('shopify_client_id'),
            'config' => $shopifyAppConfig,
        ]);
    }
}
