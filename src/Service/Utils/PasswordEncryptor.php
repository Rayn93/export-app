<?php
declare(strict_types=1);

namespace App\Service\Utils;

class PasswordEncryptor
{
    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = hash('sha256', $secretKey);
    }

    public function encrypt(string $plainPassword): string
    {
        if ($this->isEncrypted($plainPassword)) {
            return $plainPassword;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $plainPassword,
            'AES-256-CBC',
            $this->secretKey,
            0,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encryptedPassword): string
    {
        $data = base64_decode($encryptedPassword, true);

        if ($data === false || strlen($data) < 17) {
            throw new \InvalidArgumentException('Invalid encrypted string');
        }

        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);

        $decrypted = openssl_decrypt(
            $cipher,
            'AES-256-CBC',
            $this->secretKey,
            0,
            $iv
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    private function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false || strlen($decoded) < 17) {
            return false;
        }

        $iv = substr($decoded, 0, 16);
        $cipher = substr($decoded, 16);

        $decrypted = openssl_decrypt(
            $cipher,
            'AES-256-CBC',
            $this->secretKey,
            0,
            $iv
        );

        return $decrypted !== false;
    }
}
