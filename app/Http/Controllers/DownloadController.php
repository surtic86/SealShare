<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Models\ShareFile;
use App\Services\FileEncryptionService;
use App\Services\ShareService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class DownloadController extends Controller
{
    public function __construct(
        private FileEncryptionService $encryptionService,
        private ShareService $shareService,
    ) {}

    /**
     * Download all files as a ZIP archive.
     */
    public function download(Share $share): StreamedResponse
    {
        abort_if($share->isExpired() || $share->hasReachedDownloadLimit(), 404);

        $share->load('files');
        $key = $this->resolveDecryptionKey($share);

        $this->shareService->recordDownload($share);

        return new StreamedResponse(function () use ($share, $key): void {
            $zip = new ZipStream(
                outputName: 'share-'.$share->token.'.zip',
                sendHttpHeaders: false,
            );

            foreach ($share->files as $file) {
                $encryptedPath = Storage::disk('shares')->path($share->token.'/'.basename($file->stored_path));
                $callback = $this->encryptionService->decryptFileToCallback($encryptedPath, $key);

                $filename = $file->relative_path ?: $file->original_name;
                $filename = str_replace('\\', '/', $filename);

                if (str_starts_with($filename, '/') || str_contains($filename, '..')) {
                    $filename = basename($filename);
                }

                $zip->addFileFromCallback(fileName: $filename, callback: $callback, exactSize: $file->file_size);
            }

            $zip->finish();
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="share-'.$share->token.'.zip"',
        ]);
    }

    /**
     * Download a single file.
     */
    public function downloadFile(Share $share, ShareFile $shareFile): StreamedResponse
    {
        abort_if($share->isExpired() || $share->hasReachedDownloadLimit(), 404);
        abort_if($shareFile->share_id !== $share->id, 404);

        $key = $this->resolveDecryptionKey($share);

        $this->shareService->recordDownload($share);

        $encryptedPath = Storage::disk('shares')->path($share->token.'/'.basename($shareFile->stored_path));

        return $this->encryptionService->decryptFileStream(
            $encryptedPath,
            $key,
            $shareFile->original_name,
            $shareFile->mime_type ?? 'application/octet-stream',
            $shareFile->file_size,
        );
    }

    /**
     * Resolve the decryption key from session or share.
     */
    private function resolveDecryptionKey(Share $share): string
    {
        if ($share->isPasswordProtected()) {
            $key = session('share_key_'.$share->token);

            abort_if(! $key, 403, 'Password required');

            return $key;
        }

        return $this->shareService->getDecryptionKey($share);
    }
}
