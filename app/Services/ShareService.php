<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Share;
use App\Models\ShareFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ShareService
{
    public function __construct(
        private FileEncryptionService $encryptionService,
    ) {}

    /**
     * Create a new share with encrypted files.
     *
     * @param  array<int, array{file: UploadedFile, relativePath: string|null}>  $files
     * @param  array{password?: string|null, expires_at?: string|null, max_downloads?: int|null}  $options
     */
    public function createShare(array $files, array $options = []): Share
    {
        $token = $this->generateUniqueToken();
        $salt = $this->encryptionService->generateSalt();
        $password = $options['password'] ?? null;

        if ($password) {
            $encryptionKey = $this->encryptionService->deriveKey($password, $salt);
            $encryptionKeyHex = bin2hex($encryptionKey);
            $storedEncryptionKey = null;
        } else {
            $encryptionKeyHex = $this->encryptionService->generateRandomKey();
            $storedEncryptionKey = $encryptionKeyHex;
        }

        $share = Share::query()->create([
            'token' => $token,
            'password' => $password ? Hash::make($password) : null,
            'encryption_key' => $storedEncryptionKey,
            'encryption_salt' => $salt,
            'expires_at' => $options['expires_at'] ?? null,
            'max_downloads' => $options['max_downloads'] ?? null,
            'total_size' => 0,
        ]);

        $totalSize = 0;

        foreach ($files as $fileData) {
            /** @var UploadedFile $file */
            $file = $fileData['file'];
            $relativePath = $fileData['relativePath'] ?? null;
            $storedName = Str::uuid().'.enc';
            $storedPath = 'shares/'.$share->token.'/'.$storedName;

            $tempPath = $file->getRealPath();
            $destPath = Storage::disk('shares')->path($share->token.'/'.$storedName);

            Storage::disk('shares')->makeDirectory($share->token);

            $this->encryptionService->encryptFile($tempPath, $destPath, $encryptionKeyHex);

            ShareFile::query()->create([
                'share_id' => $share->id,
                'original_name' => $file->getClientOriginalName(),
                'relative_path' => $relativePath,
                'stored_path' => $storedPath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            $totalSize += $file->getSize();
        }

        $share->update(['total_size' => $totalSize]);

        return $share->fresh();
    }

    /**
     * Generate a unique share token with retry on collision.
     */
    private function generateUniqueToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = Str::random(16);

            if (! Share::query()->where('token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Unable to generate a unique share token');
    }

    /**
     * Delete a share and its files from disk.
     */
    public function deleteShare(Share $share): void
    {
        Storage::disk('shares')->deleteDirectory($share->token);

        $share->delete();
    }

    /**
     * Get the decryption key for a share.
     */
    public function getDecryptionKey(Share $share, ?string $password = null): string
    {
        if ($share->isPasswordProtected()) {
            if (! $password) {
                throw new \RuntimeException('Password required for this share');
            }

            return bin2hex($this->encryptionService->deriveKey($password, $share->encryption_salt));
        }

        return $share->encryption_key;
    }

    /**
     * Verify a password against a share's stored hash.
     */
    public function verifyPassword(Share $share, string $password): bool
    {
        if (! $share->isPasswordProtected()) {
            return true;
        }

        return Hash::check($password, $share->password);
    }

    /**
     * Record a download and auto-delete if limit reached.
     */
    public function recordDownload(Share $share): void
    {
        $share->increment('download_count');

        if ($share->hasReachedDownloadLimit()) {
            $this->deleteShare($share);
        }
    }

    /**
     * Get total used space in bytes.
     */
    public function getTotalUsedSpace(): int
    {
        return (int) Share::query()->sum('total_size');
    }

    /**
     * Check if storage is full based on admin-configured max quota.
     */
    public function isStorageFull(): bool
    {
        $maxQuota = (int) Setting::get('max_storage_quota', 20 * 1024 * 1024 * 1024);

        return $this->getTotalUsedSpace() >= $maxQuota;
    }

    /**
     * Get the maximum storage quota in bytes.
     */
    public function getMaxStorageQuota(): int
    {
        return (int) Setting::get('max_storage_quota', 20 * 1024 * 1024 * 1024);
    }
}
