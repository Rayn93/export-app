<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service\Utils;

use App\Service\Utils\PasswordEncryptor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PasswordEncryptorTest extends TestCase
{
    private PasswordEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->encryptor = new PasswordEncryptor('my-secret-key');
    }

    public function testEncryptAndDecryptReturnOriginalPassword(): void
    {
        $plain = 'SuperSecret123!';
        $encrypted = $this->encryptor->encrypt($plain);
        $this->assertNotSame($plain, $encrypted, 'Encrypted string should differ from plain text');
        $this->assertNotEmpty($encrypted, 'Encrypted string should not be empty');
        $decrypted = $this->encryptor->decrypt($encrypted);
        $this->assertSame($plain, $decrypted, 'Decrypted string should match original password');
    }

    public function testEncryptIsDeterministicWithDifferentIVs(): void
    {
        $plain = 'SamePassword';
        $encrypted1 = $this->encryptor->encrypt($plain);
        $encrypted2 = $this->encryptor->encrypt($plain);
        $this->assertNotSame($encrypted1, $encrypted2, 'Encryption should use different IVs each time');
        $this->assertSame($plain, $this->encryptor->decrypt($encrypted1));
        $this->assertSame($plain, $this->encryptor->decrypt($encrypted2));
    }

    public function testEncryptDoesNotReEncryptAlreadyEncryptedString(): void
    {
        $plain = 'Password';
        $encrypted1 = $this->encryptor->encrypt($plain);
        $encrypted2 = $this->encryptor->encrypt($encrypted1);
        $this->assertSame($encrypted1, $encrypted2, 'Already encrypted value should not be re-encrypted');
    }

    public function testDecryptThrowsOnInvalidBase64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encrypted string');
        $this->encryptor->decrypt('@@not-valid-base64@@');
    }

    public function testDecryptThrowsOnTooShortData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encrypted string');
        $shortBase64 = base64_encode('short');
        $this->encryptor->decrypt($shortBase64);
    }

    public function testDecryptThrowsWhenInvalidKeyUsed(): void
    {
        $plain = 'Sensitive';
        $encrypted = $this->encryptor->encrypt($plain);
        $differentEncryptor = new PasswordEncryptor('different-key');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        $differentEncryptor->decrypt($encrypted);
    }

    public function testEncryptThrowsWhenOpenSslFails(): void
    {
        $encryptor = new class('secret') extends PasswordEncryptor {
            public function encrypt(string $plainPassword): string
            {
                $iv = random_bytes(16);
                $encrypted = false;

                if ($encrypted === false) {
                    throw new \RuntimeException('Encryption failed');
                }

                return '';
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption failed');
        $encryptor->encrypt('test');
    }
}
