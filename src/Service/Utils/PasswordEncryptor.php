<?php
declare(strict_types=1);

namespace App\Service\Utils;

final class PasswordEncryptor
{
    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = hash('sha256', $secretKey);
    }

    public function encrypt(string $plainPassword): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $plainPassword,
            'AES-256-CBC',
            $this->secretKey,
            0,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encryptedPassword): string
    {
        $data = base64_decode($encryptedPassword);
        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);

        return openssl_decrypt(
            $cipher,
            'AES-256-CBC',
            $this->secretKey,
            0,
            $iv
        );
    }
}