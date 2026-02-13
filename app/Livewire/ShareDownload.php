<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\Share;
use App\Services\ShareService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class ShareDownload extends Component
{
    public Share $share;

    public bool $authenticated = false;

    #[Validate('required|string')]
    public string $password = '';

    public function mount(Share $share): void
    {
        $this->share = $share->load('files');

        if ($share->isExpired() || $share->hasReachedDownloadLimit()) {
            abort(404);
        }

        if (! $share->isPasswordProtected()) {
            $this->authenticated = true;
        }

        if ($share->isPasswordProtected() && session('share_key_'.$share->token)) {
            $this->authenticated = true;
        }
    }

    public function verifyPassword(ShareService $shareService): void
    {
        $rateLimitKey = 'share-password:'.$this->share->token.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError('password', __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]));

            return;
        }

        $this->validate();

        if (! $shareService->verifyPassword($this->share, $this->password)) {
            RateLimiter::hit($rateLimitKey, 60);
            $this->addError('password', __('The password is incorrect.'));

            return;
        }

        RateLimiter::clear($rateLimitKey);

        $encryptionKey = $shareService->getDecryptionKey($this->share, $this->password);
        session(['share_key_'.$this->share->token => $encryptionKey]);
        $this->authenticated = true;
    }

    public function render(): mixed
    {
        return view('livewire.share-download', [
            'siteTitle' => Setting::get('site_title'),
            'siteDescription' => Setting::get('site_description'),
            'siteLogo' => Setting::get('site_logo'),
        ]);
    }
}
