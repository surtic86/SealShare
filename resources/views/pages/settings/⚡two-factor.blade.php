<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\Response;

new class extends Component {
    #[Locked]
    public bool $twoFactorEnabled;

    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        abort_unless(Features::enabled(Features::twoFactorAuthentication()), Response::HTTP_FORBIDDEN);

        if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
            $disableTwoFactorAuthentication(auth()->user());
        }

        $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        $enableTwoFactorAuthentication(auth()->user());

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user();

        try {
            $this->qrCodeSvg = $user?->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }
    }

    /**
     * Get the current modal configuration state.
     */
    public function getModalConfigProperty(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-Factor Authentication Enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify Authentication Code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable Two-Factor Authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
} ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        :heading="__('Two Factor Authentication')"
        :subheading="__('Manage your two-factor authentication settings')"
    >
        <div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
            @if ($twoFactorEnabled)
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="badge badge-success">{{ __('Enabled') }}</span>
                    </div>

                    <p class="opacity-70">
                        {{ __('With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                    </p>

                    <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />

                    <div class="flex justify-start">
                        <x-button
                            label="{{ __('Disable 2FA') }}"
                            icon="o-shield-exclamation"
                            class="btn-error"
                            wire:click="disable"
                        />
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="badge badge-error">{{ __('Disabled') }}</span>
                    </div>

                    <p class="opacity-60">
                        {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                    </p>

                    <x-button
                        label="{{ __('Enable 2FA') }}"
                        icon="o-shield-check"
                        class="btn-primary"
                        wire:click="enable"
                    />
                </div>
            @endif
        </div>
    </x-pages::settings.layout>

    <x-modal wire:model="showModal" :title="$this->modalConfig['title']" class="max-w-md">
        <p class="text-sm opacity-70">{{ $this->modalConfig['description'] }}</p>

        @if ($showVerificationStep)
            <div class="space-y-6 mt-4">
                <div class="flex flex-col items-center space-y-3 justify-center">
                    <x-input
                        name="code"
                        wire:model="code"
                        placeholder="000000"
                        class="text-center text-2xl tracking-widest"
                        maxlength="6"
                    />
                </div>

                <x-slot:actions>
                    <x-button
                        label="{{ __('Back') }}"
                        wire:click="resetVerification"
                    />
                    <x-button
                        label="{{ __('Confirm') }}"
                        class="btn-primary"
                        wire:click="confirmTwoFactor"
                        x-bind:disabled="$wire.code.length < 6"
                    />
                </x-slot:actions>
            </div>
        @else
            @error('setupData')
                <div class="alert alert-error mt-4">{{ $message }}</div>
            @enderror

            <div class="flex justify-center mt-4">
                <div class="relative w-64 overflow-hidden border rounded-lg border-base-300 aspect-square">
                    @empty($qrCodeSvg)
                        <div class="absolute inset-0 flex items-center justify-center bg-base-200 animate-pulse">
                            <span class="loading loading-spinner"></span>
                        </div>
                    @else
                        <div class="flex items-center justify-center h-full p-4 bg-white">
                            {!! $qrCodeSvg !!}
                        </div>
                    @endempty
                </div>
            </div>

            <div class="space-y-4 mt-4">
                <div class="relative flex items-center justify-center w-full">
                    <div class="absolute inset-0 w-full h-px top-1/2 bg-base-300"></div>
                    <span class="relative px-2 text-sm bg-base-100 opacity-60">
                        {{ __('or, enter the code manually') }}
                    </span>
                </div>

                <div
                    class="flex items-center space-x-2"
                    x-data="{
                        copied: false,
                        async copy() {
                            try {
                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            } catch (e) {
                                console.warn('Could not copy to clipboard');
                            }
                        }
                    }"
                >
                    <div class="join w-full">
                        <input
                            type="text"
                            readonly
                            value="{{ $manualSetupKey }}"
                            class="input input-bordered join-item w-full"
                        />
                        <button
                            @click="copy()"
                            class="btn join-item"
                        >
                            <x-icon x-show="!copied" name="o-document-duplicate" class="w-4 h-4" />
                            <x-icon x-show="copied" name="o-check" class="w-4 h-4 text-success" />
                        </button>
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <x-button
                    :disabled="$errors->has('setupData')"
                    label="{{ $this->modalConfig['buttonText'] }}"
                    class="btn-primary"
                    wire:click="showVerificationIfNecessary"
                />
            </x-slot:actions>
        @endif
    </x-modal>
</section>
