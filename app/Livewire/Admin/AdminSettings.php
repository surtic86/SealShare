<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class AdminSettings extends Component
{
    use WithFileUploads;

    public string $systemPassword = '';

    public string $defaultExpiration = '';

    public int $maxFileSize = 100;

    public int $maxStorageQuota = 20;

    public int $maxFilesPerShare = 50;

    public int $maxSizePerShare = 2;

    public bool $allowNeverExpire = false;

    public string $siteTitle = '';

    public string $siteDescription = '';

    public $siteLogo;

    public function mount(): void
    {
        $this->defaultExpiration = Setting::get('default_expiration', '') ?? '';
        $this->maxFileSize = min(
            (int) Setting::get('max_file_size', 100 * 1024 * 1024) / (1024 * 1024),
            self::phpMaxUploadMb(),
        );
        $this->maxStorageQuota = (int) Setting::get('max_storage_quota', 20 * 1024 * 1024 * 1024) / (1024 * 1024 * 1024);
        $this->maxFilesPerShare = (int) Setting::get('max_files_per_share', 50);
        $this->maxSizePerShare = (int) Setting::get('max_size_per_share', 2 * 1024 * 1024 * 1024) / (1024 * 1024 * 1024);
        $this->allowNeverExpire = (bool) Setting::get('allow_never_expire', false);
        $this->siteTitle = Setting::get('site_title', '') ?? '';
        $this->siteDescription = Setting::get('site_description', '') ?? '';
    }

    public static function phpMaxUploadMb(): int
    {
        $parse = function (string $value): int {
            $value = trim($value);
            $last = strtolower($value[strlen($value) - 1]);
            $num = (int) $value;

            return match ($last) {
                'g' => $num * 1024,
                'm' => $num,
                'k' => max(1, (int) ($num / 1024)),
                default => max(1, (int) ($num / (1024 * 1024))),
            };
        };

        $upload = $parse(ini_get('upload_max_filesize') ?: '2M');
        $post = $parse(ini_get('post_max_size') ?: '8M');

        return min($upload, $post);
    }

    public function saveSettings(): void
    {
        $phpMaxMb = self::phpMaxUploadMb();

        $this->validate([
            'maxFileSize' => ['required', 'integer', 'min:1', 'max:'.$phpMaxMb],
            'maxStorageQuota' => ['required', 'integer', 'min:1'],
            'maxFilesPerShare' => ['required', 'integer', 'min:1'],
            'maxSizePerShare' => ['required', 'integer', 'min:1'],
            'siteTitle' => ['nullable', 'string', 'max:255'],
            'siteDescription' => ['nullable', 'string', 'max:1000'],
            'siteLogo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
        ], [
            'maxFileSize.max' => __('Cannot exceed the PHP limit of :max MB. Increase upload_max_filesize and post_max_size in your PHP configuration.', ['max' => $phpMaxMb]),
        ]);

        if ($this->systemPassword) {
            Setting::set('system_password', Hash::make($this->systemPassword));
        }

        Setting::set('default_expiration', $this->defaultExpiration ?: null);
        Setting::set('max_file_size', $this->maxFileSize * 1024 * 1024);
        Setting::set('max_storage_quota', $this->maxStorageQuota * 1024 * 1024 * 1024);
        Setting::set('max_files_per_share', $this->maxFilesPerShare);
        Setting::set('max_size_per_share', $this->maxSizePerShare * 1024 * 1024 * 1024);

        Setting::set('allow_never_expire', $this->allowNeverExpire ? '1' : null);
        Setting::set('site_title', $this->siteTitle ?: null);
        Setting::set('site_description', $this->siteDescription ?: null);

        if ($this->siteLogo && is_object($this->siteLogo)) {
            $existingLogo = Setting::get('site_logo');
            if ($existingLogo) {
                Storage::disk('public')->delete($existingLogo);
            }

            $path = $this->siteLogo->store('branding', 'public');
            Setting::set('site_logo', $path);
            $this->siteLogo = null;
        }

        $this->systemPassword = '';

        session()->flash('message', __('Settings saved successfully.'));
    }

    public function removeLogo(): void
    {
        $existingLogo = Setting::get('site_logo');

        if ($existingLogo) {
            Storage::disk('public')->delete($existingLogo);
            Setting::set('site_logo', null);
        }

        session()->flash('message', __('Logo removed.'));
    }

    public function clearSystemPassword(): void
    {
        Setting::set('system_password', null);

        session()->flash('message', __('System password cleared.'));
    }

    public function render(): mixed
    {
        return view('livewire.admin.admin-settings', [
            'hasSystemPassword' => (bool) Setting::get('system_password'),
            'currentLogo' => Setting::get('site_logo'),
            'phpMaxUploadMb' => self::phpMaxUploadMb(),
        ]);
    }
}
