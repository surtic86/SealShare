<?php

use App\Services\FileEncryptionService;

beforeEach(function () {
    $this->service = new FileEncryptionService;
    $this->tempDir = sys_get_temp_dir().'/sealshare-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir.'/*'));
        rmdir($this->tempDir);
    }
});

test('encrypt and decrypt round-trip works', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/encrypted.enc';
    $content = 'Hello, World! This is a secret message.';

    file_put_contents($sourcePath, $content);

    $key = $this->service->generateRandomKey();

    $this->service->encryptFile($sourcePath, $encryptedPath, $key);

    expect(file_exists($encryptedPath))->toBeTrue();
    expect(file_get_contents($encryptedPath))->not->toBe($content);

    $decrypted = $this->service->decryptFile($encryptedPath, $key);

    expect($decrypted)->toBe($content);
});

test('decrypt with wrong key fails', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/encrypted.enc';

    file_put_contents($sourcePath, 'Secret data');

    $correctKey = $this->service->generateRandomKey();
    $wrongKey = $this->service->generateRandomKey();

    $this->service->encryptFile($sourcePath, $encryptedPath, $correctKey);

    $this->service->decryptFile($encryptedPath, $wrongKey);
})->throws(RuntimeException::class, 'Decryption failed');

test('derive key produces consistent results', function () {
    $password = 'my-secure-password';
    $salt = $this->service->generateSalt();

    $key1 = $this->service->deriveKey($password, $salt);
    $key2 = $this->service->deriveKey($password, $salt);

    expect($key1)->toBe($key2);
});

test('derive key with different passwords produces different keys', function () {
    $salt = $this->service->generateSalt();

    $key1 = $this->service->deriveKey('password1', $salt);
    $key2 = $this->service->deriveKey('password2', $salt);

    expect($key1)->not->toBe($key2);
});

test('derive key with different salts produces different keys', function () {
    $password = 'same-password';

    $key1 = $this->service->deriveKey($password, $this->service->generateSalt());
    $key2 = $this->service->deriveKey($password, $this->service->generateSalt());

    expect($key1)->not->toBe($key2);
});

test('generate random key returns 64 char hex string', function () {
    $key = $this->service->generateRandomKey();

    expect(strlen($key))->toBe(64);
    expect(ctype_xdigit($key))->toBeTrue();
});

test('generate salt returns 64 char hex string', function () {
    $salt = $this->service->generateSalt();

    expect(strlen($salt))->toBe(64);
    expect(ctype_xdigit($salt))->toBeTrue();
});

test('password-derived key encrypt/decrypt round-trip works', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/encrypted.enc';
    $content = 'Password protected content';

    file_put_contents($sourcePath, $content);

    $password = 'user-password';
    $salt = $this->service->generateSalt();
    $key = bin2hex($this->service->deriveKey($password, $salt));

    $this->service->encryptFile($sourcePath, $encryptedPath, $key);
    $decrypted = $this->service->decryptFile($encryptedPath, $key);

    expect($decrypted)->toBe($content);
});

test('decrypt file stream returns streamed response', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/encrypted.enc';
    $content = 'Streamed content';

    file_put_contents($sourcePath, $content);

    $key = $this->service->generateRandomKey();
    $this->service->encryptFile($sourcePath, $encryptedPath, $key);

    $response = $this->service->decryptFileStream($encryptedPath, $key, 'test.txt', 'text/plain');

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe('text/plain');
    expect($response->headers->get('Content-Disposition'))->toContain('test.txt');
});

test('chunked file has SEALCHK1 magic header', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/encrypted.enc';

    file_put_contents($sourcePath, 'test content');

    $key = $this->service->generateRandomKey();
    $this->service->encryptFile($sourcePath, $encryptedPath, $key);

    $header = file_get_contents($encryptedPath, false, null, 0, 8);

    expect($header)->toBe('SEALCHK1');
});

test('multi-chunk round-trip works', function () {
    $sourcePath = $this->tempDir.'/large.bin';
    $encryptedPath = $this->tempDir.'/large.enc';

    // Create a file larger than one 4 MB chunk (5 MB)
    $chunkSize = 4 * 1024 * 1024;
    $content = random_bytes($chunkSize + (1024 * 1024));

    file_put_contents($sourcePath, $content);

    $key = $this->service->generateRandomKey();
    $this->service->encryptFile($sourcePath, $encryptedPath, $key);
    $decrypted = $this->service->decryptFile($encryptedPath, $key);

    expect($decrypted)->toBe($content);
});

test('exact chunk boundary round-trip works', function () {
    $sourcePath = $this->tempDir.'/exact.bin';
    $encryptedPath = $this->tempDir.'/exact.enc';

    // Create a file exactly equal to one chunk (4 MB)
    $content = random_bytes(4 * 1024 * 1024);

    file_put_contents($sourcePath, $content);

    $key = $this->service->generateRandomKey();
    $this->service->encryptFile($sourcePath, $encryptedPath, $key);
    $decrypted = $this->service->decryptFile($encryptedPath, $key);

    expect($decrypted)->toBe($content);
});

test('empty file round-trip works', function () {
    $sourcePath = $this->tempDir.'/empty.bin';
    $encryptedPath = $this->tempDir.'/empty.enc';

    file_put_contents($sourcePath, '');

    $key = $this->service->generateRandomKey();
    $this->service->encryptFile($sourcePath, $encryptedPath, $key);
    $decrypted = $this->service->decryptFile($encryptedPath, $key);

    expect($decrypted)->toBe('');
});

test('legacy format backward compatibility', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/legacy.enc';
    $content = 'Legacy encrypted content';

    file_put_contents($sourcePath, $content);

    $key = $this->service->generateRandomKey();
    $binaryKey = hex2bin($key);

    // Manually create a legacy format file: [nonce][tag][ciphertext]
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($content, 'aes-256-gcm', $binaryKey, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    file_put_contents($encryptedPath, $nonce.$tag.$ciphertext);

    $decrypted = $this->service->decryptFile($encryptedPath, $key);

    expect($decrypted)->toBe($content);
});

test('wrong key on chunked file throws exception', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/encrypted.enc';

    file_put_contents($sourcePath, 'Chunked secret data');

    $correctKey = $this->service->generateRandomKey();
    $wrongKey = $this->service->generateRandomKey();

    $this->service->encryptFile($sourcePath, $encryptedPath, $correctKey);

    $this->service->decryptFile($encryptedPath, $wrongKey);
})->throws(RuntimeException::class, 'Decryption failed');


test('decrypt file stream with file size sets content-length header', function () {
    $sourcePath = $this->tempDir.'/source.txt';
    $encryptedPath = $this->tempDir.'/encrypted.enc';
    $content = 'Content with known size';

    file_put_contents($sourcePath, $content);

    $key = $this->service->generateRandomKey();
    $this->service->encryptFile($sourcePath, $encryptedPath, $key);

    $response = $this->service->decryptFileStream($encryptedPath, $key, 'test.txt', 'text/plain', strlen($content));

    expect($response->headers->get('Content-Length'))->toBe((string) strlen($content));
});
