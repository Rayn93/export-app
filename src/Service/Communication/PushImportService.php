<?php

declare(strict_types=1);

namespace App\Service\Communication;

use App\Entity\ShopifyAppConfig;
use App\Service\Communication\Exception\ImportRunningException;
use App\Service\Utils\PasswordEncryptor;
use Omikron\FactFinder\Communication\Client\ClientBuilder;
use Omikron\FactFinder\Communication\Credentials;
use Omikron\FactFinder\Communication\Resource\NG\ImportAdapter;

readonly class PushImportService
{
    const IMPORT_TYPES = ['search', 'recommendation', 'suggest'];
    public function __construct(private PasswordEncryptor $passwordEncryptor)
    {
    }

    public function execute(ShopifyAppConfig $shopifyAppConfig): void
    {
        $channelName = $shopifyAppConfig->getFfChannelName();
        $apiServerUrl = $shopifyAppConfig->getFfApiServerUrl();
        $apiUsername = $shopifyAppConfig->getFfApiUsername();
        $apiPassword = $shopifyAppConfig->getFfApiPassword();

        if (!empty($apiServerUrl) && !empty($apiUsername) && !empty($apiPassword)) {
            $importAdapter = $this->getImportAdapter($apiServerUrl, $apiUsername, $apiPassword);
            $this->checkNotRunning($importAdapter, $channelName);

            foreach (PushImportService::IMPORT_TYPES as $exportType) {
                $importAdapter->import($channelName, $exportType);
            }
        }
    }

    private function checkNotRunning(ImportAdapter $importAdapter, string $channel): void
    {
        if ($importAdapter->running($channel)) {
            throw new ImportRunningException();
        }
    }

    private function getImportAdapter(string $apiServerUrl, string $apiUsername, string $apiPassword): ImportAdapter
    {
        $client = (new ClientBuilder())
            ->withServerUrl($apiServerUrl)
            ->withCredentials(new Credentials($apiUsername, $this->passwordEncryptor->decrypt($apiPassword)))
            ->build();

        return new ImportAdapter($client, ['api_version' => 'v5']);
    }
}
