<?php

namespace App\Services;

use Generator;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileEncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    private const PBKDF2_ITERATIONS = 100000;

    private const KEY_LENGTH = 32;

    private const NONCE_LENGTH = 12;

    private const TAG_LENGTH = 16;

    private const MAGIC_HEADER = 'SEALCHK1';

    private const DEFAULT_CHUNK_SIZE = 4 * 1024 * 1024; // 4 MB

    /**
     * Derive an encryption key from a password and salt using PBKDF2-SHA256.
     */
    public function deriveKey(string $password, string $salt): string
    {
        return hash_pbkdf2('sha256', $password, hex2bin($salt), self::PBKDF2_ITERATIONS, self::KEY_LENGTH, true);
    }

    /**
     * Generate a random hex salt (32 bytes = 64 hex chars).
     */
    public function generateSalt(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a random encryption key (32 bytes, returned as hex).
     */
    public function generateRandomKey(): string
    {
        return bin2hex(random_bytes(self::KEY_LENGTH));
    }

    /**
     * Encrypt a file using chunked AES-256-GCM.
     *
     * Output format:
     *   [8 bytes: "SEALCHK1" magic]
     *   [4 bytes: chunk size, uint32 big-endian]
     *   [12 bytes: base nonce]
     *   Per chunk:
     *     [16 bytes: GCM auth tag]
     *     [N bytes: ciphertext (up to chunk_size)]
     */
    public function encryptFile(string $sourcePath, string $destPath, string $key): void
    {
        $source = fopen($sourcePath, 'rb');

        if ($source === false) {
            throw new RuntimeException("Cannot read source file: {$sourcePath}");
        }

        $dest = fopen($destPath, 'wb');

        if ($dest === false) {
            fclose($source);

            throw new RuntimeException("Cannot write encrypted file: {$destPath}");
        }

        try {
            $binaryKey = $this->normalizeToBinaryKey($key);
            $baseNonce = random_bytes(self::NONCE_LENGTH);
            $chunkSize = self::DEFAULT_CHUNK_SIZE;

            // Write header
            fwrite($dest, self::MAGIC_HEADER);
            fwrite($dest, pack('N', $chunkSize));
            fwrite($dest, $baseNonce);

            $chunkIndex = 0;

            while (! feof($source)) {
                $plaintext = fread($source, $chunkSize);

                if ($plaintext === false || $plaintext === '') {
                    break;
                }

                $nonce = $this->deriveChunkNonce($baseNonce, $chunkIndex);
                $tag = '';

                $ciphertext = openssl_encrypt(
                    $plaintext,
                    self::CIPHER,
                    $binaryKey,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    '',
                    self::TAG_LENGTH,
                );

                if ($ciphertext === false) {
                    throw new RuntimeException('Encryption failed at chunk '.$chunkIndex);
                }

                fwrite($dest, $tag);
                fwrite($dest, $ciphertext);
                $chunkIndex++;
            }
        } catch (RuntimeException $e) {
            fclose($source);
            fclose($dest);
            @unlink($destPath);

            throw $e;
        }

        fclose($source);
        fclose($dest);
    }

    /**
     * Decrypt a file and return the plaintext content.
     */
    public function decryptFile(string $encryptedPath, string $key): string
    {
        if ($this->isChunkedFormat($encryptedPath)) {
            $parts = [];

            foreach ($this->decryptChunks($encryptedPath, $key) as $chunk) {
                $parts[] = $chunk;
            }

            return implode('', $parts);
        }

        return $this->decryptLegacy($encryptedPath, $key);
    }

    /**
     * Decrypt a file and stream the response.
     */
    public function decryptFileStream(string $encryptedPath, string $key, string $filename, string $mimeType, ?int $fileSize = null): StreamedResponse
    {
        $headers = [
            'Content-Type' => $mimeType ?: 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename, 'download'),
        ];

        if ($fileSize !== null) {
            $headers['Content-Length'] = $fileSize;
        }

        if ($this->isChunkedFormat($encryptedPath)) {
            return new StreamedResponse(function () use ($encryptedPath, $key): void {
                foreach ($this->decryptChunks($encryptedPath, $key) as $chunk) {
                    echo $chunk;
                    flush();
                }
            }, 200, $headers);
        }

        $content = $this->decryptLegacy($encryptedPath, $key);

        if (! isset($headers['Content-Length'])) {
            $headers['Content-Length'] = strlen($content);
        }

        return new StreamedResponse(function () use ($content): void {
            echo $content;
        }, 200, $headers);
    }

    /**
     * Stream decrypted file content directly to output (echo).
     * Use this when you need to add post-streaming logic inside a StreamedResponse callback.
     */
    public function streamDecryptedFile(string $encryptedPath, string $key): void
    {
        if ($this->isChunkedFormat($encryptedPath)) {
            foreach ($this->decryptChunks($encryptedPath, $key) as $chunk) {
                echo $chunk;
                flush();
            }

            return;
        }

        echo $this->decryptLegacy($encryptedPath, $key);
    }

    /**
     * Normalize a hex key to binary.
     */
    private function normalizeToBinaryKey(string $key): string
    {
        return strlen($key) === 64 ? hex2bin($key) : $key;
    }

    /**
     * Derive a unique nonce for a chunk by XORing the chunk index into the last 4 bytes.
     */
    private function deriveChunkNonce(string $baseNonce, int $chunkIndex): string
    {
        $nonce = $baseNonce;
        $indexBytes = pack('N', $chunkIndex);

        for ($i = 0; $i < 4; $i++) {
            $nonce[self::NONCE_LENGTH - 4 + $i] = $nonce[self::NONCE_LENGTH - 4 + $i] ^ $indexBytes[$i];
        }

        return $nonce;
    }

    /**
     * Check if a file uses the chunked encryption format.
     */
    private function isChunkedFormat(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 8);
        fclose($handle);

        return $magic === self::MAGIC_HEADER;
    }

    /**
     * Decrypt a legacy single-block encrypted file.
     * Format: [12-byte nonce][16-byte auth tag][ciphertext]
     */
    private function decryptLegacy(string $encryptedPath, string $key): string
    {
        $data = file_get_contents($encryptedPath);

        if ($data === false) {
            throw new RuntimeException("Cannot read encrypted file: {$encryptedPath}");
        }

        $binaryKey = $this->normalizeToBinaryKey($key);
        $nonce = substr($data, 0, self::NONCE_LENGTH);
        $tag = substr($data, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($data, self::NONCE_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $binaryKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed - wrong key or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Generator that yields decrypted plaintext chunks from a chunked encrypted file.
     *
     * @return Generator<int, string>
     */
    private function decryptChunks(string $encryptedPath, string $key): Generator
    {
        $handle = fopen($encryptedPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot read encrypted file: {$encryptedPath}");
        }

        try {
            // Read header
            $magic = fread($handle, 8);

            if ($magic !== self::MAGIC_HEADER) {
                throw new RuntimeException('Invalid chunked file format');
            }

            $chunkSizeData = fread($handle, 4);
            $chunkSize = unpack('N', $chunkSizeData)[1];

            $baseNonce = fread($handle, self::NONCE_LENGTH);

            if (strlen($baseNonce) !== self::NONCE_LENGTH) {
                throw new RuntimeException('Invalid chunked file: truncated header');
            }

            $binaryKey = $this->normalizeToBinaryKey($key);
            $chunkIndex = 0;

            while (! feof($handle)) {
                $tag = fread($handle, self::TAG_LENGTH);

                if ($tag === false || strlen($tag) === 0) {
                    break;
                }

                if (strlen($tag) !== self::TAG_LENGTH) {
                    throw new RuntimeException('Invalid chunked file: truncated tag at chunk '.$chunkIndex);
                }

                $ciphertext = fread($handle, $chunkSize);

                if ($ciphertext === false || $ciphertext === '') {
                    throw new RuntimeException('Invalid chunked file: missing ciphertext at chunk '.$chunkIndex);
                }

                $nonce = $this->deriveChunkNonce($baseNonce, $chunkIndex);

                $plaintext = openssl_decrypt(
                    $ciphertext,
                    self::CIPHER,
                    $binaryKey,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                );

                if ($plaintext === false) {
                    throw new RuntimeException('Decryption failed - wrong key or corrupted data');
                }

                yield $plaintext;
                $chunkIndex++;
            }
        } finally {
            fclose($handle);
        }
    }
}
