<?php

declare(strict_types=1);

namespace App\Service\Upload;

use App\Config\Enum\Protocol;
use App\Entity\ShopifyAppConfig;
use App\Service\Utils\PasswordEncryptor;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

class UploadService
{
    public function __construct(private PasswordEncryptor $passwordEncryptor)
    {
    }

    public function uploadForShopifyConfig(ShopifyAppConfig $shopifyConfig, string $file, string $filename): bool
    {
        return $this->uploadFile(
            $file,
            $filename,
            $shopifyConfig->getServerUrl(),
            $shopifyConfig->getUsername(),
            trim($shopifyConfig->getPrivateKeyContent()),
            $shopifyConfig->getKeyPassphrase(),
            $shopifyConfig->getPort() ?: 21,
            $shopifyConfig->getRootDirectory() ?: '/',
            Protocol::SFTP === $shopifyConfig->getProtocol()
        );
    }

    public function uploadFile(
        string $localFilePath,
        string $filename,
        string $host,
        string $username,
        string $privateKey,
        string $passphrase,
        int $port,
        string $path,
        bool $useSftp,
    ): bool {

        try {
            if ($useSftp) {
                $adapter = new SftpAdapter(
                    SftpConnectionProvider::fromArray([
                        'host'       => $host,
                        'port'       => $port,
                        'username'   => $username,
                        'privateKey' => $privateKey,
                        'passphrase' => $this->passwordEncryptor->decrypt($passphrase),
                        'timeout'    => 25,
                    ]),
                    $path
                );
            } else {
                $adapter = new FtpAdapter(
                    FtpConnectionOptions::fromArray([
                        'host'     => $host,
                        'port'     => $port,
                        'username' => $username,
                        'password' => $this->passwordEncryptor->decrypt($passphrase),
                        'root'     => $path,
                        'timeout'  => 25,
                    ])
                );
            }

            $filesystem = new Filesystem($adapter);
            $stream = fopen($localFilePath, 'r');

            if (false === $stream) {
                throw new \RuntimeException("Could not create a file in: $localFilePath");
            }

            $filesystem->writeStream($filename, $stream);
            fclose($stream);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
