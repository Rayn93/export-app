<?php
declare(strict_types=1);

namespace App\Service\Upload;

use League\Csv\Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;


class UploadService
{
    public function upload(string $csvData, string $filename, string $host, string $username, string $privateKey, string $passphrase, int $port, string $path, bool $useSftp): bool
    {
        try {
            if ($useSftp) {
                $adapter = new SftpAdapter(
                    SftpConnectionProvider::fromArray([
                        'host' => $host,
                        'port' => $port,
                        'username' => $username,
                        'privateKey' => $privateKey,
                        'passphrase' => $passphrase,
                    ]),
                    $path
                );
            } else {
                $adapter = new FtpAdapter(
                    FtpConnectionOptions::fromArray([
                        'host' => $host,
                        'port' => $port,
                        'username' => $username,
                        'password' => $passphrase,
                        'root' => $path,
                    ])
                );
            }

            $filesystem = new Filesystem($adapter);
            $filesystem->write($filename, $csvData);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}