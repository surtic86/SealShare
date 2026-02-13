<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Services\ShareService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class FileUploader extends Component
{
    use WithFileUploads;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    /** @var array<int, string|null> */
    public array $relativePaths = [];

    public bool $usePassword = false;

    public string $password = '';

    public string $password_confirmation = '';

    public string $expiration = '7d';

    public ?int $maxDownloads = null;

    public function mount(): void
    {
        $this->expiration = Setting::get('default_expiration', '7d') ?: '7d';
    }

    public function _uploadErrored($name, $errorsInJson, $isMultiple): void
    {
        $this->dispatch('upload:errored', name: $name)->self();

        $maxFileSize = (int) Setting::get('max_file_size', 100 * 1024 * 1024);
        $maxFileSizeMb = (int) ($maxFileSize / (1024 * 1024));

        if (! is_null($errorsInJson)) {
            $errors = json_decode($errorsInJson, true)['errors'] ?? null;

            if ($errors) {
                $messages = [];
                foreach ($errors as $messages_array) {
                    foreach ((array) $messages_array as $msg) {
                        $messages[] = $msg;
                    }
                }

                throw ValidationException::withMessages([
                    'files' => __('Upload failed: file exceeds the maximum size of :max MB.', ['max' => $maxFileSizeMb]),
                ]);
            }
        }

        throw ValidationException::withMessages([
            'files' => __('Upload failed: file may be too large (max :max MB) or the connection was interrupted.', ['max' => $maxFileSizeMb]),
        ]);
    }

    public function updatedFiles(): void
    {
        $maxFileSize = (int) Setting::get('max_file_size', 100 * 1024 * 1024);
        $maxFileSizeMb = $maxFileSize / (1024 * 1024);
        $maxFilesPerShare = (int) Setting::get('max_files_per_share', 50);

        $this->resetErrorBag('files');

        if (count($this->files) > $maxFilesPerShare) {
            $this->addError('files', __('Too many files. Maximum :max files allowed per share.', ['max' => $maxFilesPerShare]));

            return;
        }

        foreach ($this->files as $file) {
            if ($file->getSize() > $maxFileSize) {
                $this->addError('files', __('":name" is too large (:size MB). Maximum file size is :max MB.', [
                    'name' => $file->getClientOriginalName(),
                    'size' => round($file->getSize() / (1024 * 1024), 1),
                    'max' => (int) $maxFileSizeMb,
                ]));

                return;
            }
        }
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index], $this->relativePaths[$index]);
        $this->files = array_values($this->files);
        $this->relativePaths = array_values($this->relativePaths);
    }

    public function createShare(ShareService $shareService): void
    {
        $maxFilesPerShare = (int) Setting::get('max_files_per_share', 50);
        $maxSizePerShare = (int) Setting::get('max_size_per_share', 2 * 1024 * 1024 * 1024);
        $maxFileSize = (int) Setting::get('max_file_size', 100 * 1024 * 1024);

        $rules = [
            'files' => ['required', 'array', 'min:1', 'max:'.$maxFilesPerShare],
            'files.*' => ['required', 'file', 'max:'.($maxFileSize / 1024)],
        ];

        $allowNeverExpire = (bool) Setting::get('allow_never_expire', false);

        if (! $allowNeverExpire) {
            $rules['expiration'] = ['required', 'string', 'in:1h,24h,48h,7d,14d,30d'];
        }

        if ($this->usePassword) {
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        $this->validate($rules, [
            'expiration.required' => __('An expiration time is required.'),
            'files.required' => __('Please select at least one file to upload.'),
            'files.max' => __('Too many files. Maximum :max files allowed per share.'),
            'files.*.max' => __('A file exceeds the maximum size of :max KB.'),
        ]);

        if ($shareService->isStorageFull()) {
            $this->addError('files', __('Storage is full. Please contact the administrator.'));

            return;
        }

        $totalSize = collect($this->files)->sum(fn ($file) => $file->getSize());

        if ($totalSize > $maxSizePerShare) {
            $this->addError('files', __('Total file size exceeds the maximum allowed per share.'));

            return;
        }

        $fileData = [];
        foreach ($this->files as $index => $file) {
            $relativePath = $this->relativePaths[$index] ?? null;

            if ($relativePath !== null) {
                $relativePath = str_replace('\\', '/', $relativePath);

                if (str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
                    $relativePath = null;
                }
            }

            $fileData[] = [
                'file' => $file,
                'relativePath' => $relativePath,
            ];
        }

        $expiresAt = match ($this->expiration) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '48h' => now()->addDays(2),
            '7d' => now()->addWeek(),
            '14d' => now()->addDays(14),
            '30d' => now()->addMonth(),
            default => null,
        };

        $share = $shareService->createShare($fileData, [
            'password' => $this->usePassword ? $this->password : null,
            'expires_at' => $expiresAt,
            'max_downloads' => $this->maxDownloads ?: null,
        ]);

        $this->redirect(route('share.created', $share), navigate: true);
    }

    public function render(): mixed
    {
        $shareService = app(ShareService::class);

        return view('livewire.file-uploader', [
            'isStorageFull' => $shareService->isStorageFull(),
            'siteTitle' => Setting::get('site_title'),
            'siteDescription' => Setting::get('site_description'),
            'siteLogo' => Setting::get('site_logo'),
            'allowNeverExpire' => (bool) Setting::get('allow_never_expire', false),
        ]);
    }
}
