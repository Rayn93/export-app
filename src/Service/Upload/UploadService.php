<?php
declare(strict_types=1);

namespace App\Service\Upload;

use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\ConnectionProvider;
use League\Flysystem\PhpseclibV3\SftpAdapter;


class UploadService
{
    public function upload(string $csvData, string $filename, string $host, string $username, string $password, int $port, string $path, bool $useSftp): bool
    {
        try {
//            if ($useSftp) {
//                $adapter = new SftpAdapter(
//                    ConnectionProvider::fromArray([
//                        'host' => $host,
//                        'port' => $port,
//                        'username' => $username,
//                        'password' => $password,
//                        'root' => $path,
//                    ])
//                );
//            } else {
                $adapter = new FtpAdapter(
                    FtpConnectionOptions::fromArray([
                        'host' => $host,
                        'port' => $port,
                        'username' => $username,
                        'password' => $password,
                        'root' => $path,
                    ])
                );

            $filesystem = new Filesystem($adapter);
            $filesystem->write($filename, $csvData);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}