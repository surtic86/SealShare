<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Models\ShareFile;
use App\Services\FileEncryptionService;
use App\Services\ShareService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DownloadController extends Controller
{
    public function __construct(
        private FileEncryptionService $encryptionService,
        private ShareService $shareService,
    ) {}

    /**
     * Download all files as a ZIP archive.
     */
    public function download(Share $share): BinaryFileResponse
    {
        abort_if($share->isExpired() || $share->hasReachedDownloadLimit(), 404);

        $share->load('files');
        $key = $this->resolveDecryptionKey($share);

        $tempPath = tempnam(sys_get_temp_dir(), 'sealshare_');

        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($share->files as $file) {
            $encryptedPath = Storage::disk('shares')->path($share->token.'/'.basename($file->stored_path));
            $content = $this->encryptionService->decryptFile($encryptedPath, $key);

            $filename = $file->relative_path ?: $file->original_name;
            $filename = str_replace('\\', '/', $filename);

            if (str_starts_with($filename, '/') || str_contains($filename, '..')) {
                $filename = basename($filename);
            }

            $zip->addFromString($filename, $content);
        }

        $zip->close();

        $this->shareService->recordDownload($share);

        return response()->download($tempPath, 'share-'.$share->token.'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Download a single file.
     */
    public function downloadFile(Share $share, ShareFile $shareFile): StreamedResponse
    {
        abort_if($share->isExpired() || $share->hasReachedDownloadLimit(), 404);
        abort_if($shareFile->share_id !== $share->id, 404);

        $key = $this->resolveDecryptionKey($share);

        $encryptedPath = Storage::disk('shares')->path($share->token.'/'.basename($shareFile->stored_path));
        $mimeType = $shareFile->mime_type ?? 'application/octet-stream';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition(
                'attachment',
                $shareFile->original_name,
                'download',
            ),
        ];

        if ($shareFile->file_size !== null) {
            $headers['Content-Length'] = $shareFile->file_size;
        }

        return new StreamedResponse(function () use ($encryptedPath, $key, $share): void {
            $this->encryptionService->streamDecryptedFile($encryptedPath, $key);

            $this->shareService->recordDownload($share);
        }, 200, $headers);
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
