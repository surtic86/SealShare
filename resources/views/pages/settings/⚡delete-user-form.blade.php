<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';
    public bool $showDeleteModal = false;

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h3 class="text-lg font-semibold">{{ __('Delete account') }}</h3>
        <p class="text-sm opacity-60">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <x-button
        label="{{ __('Delete account') }}"
        class="btn-error"
        @click="$wire.showDeleteModal = true"
        data-test="delete-user-button"
    />

    <x-modal wire:model="showDeleteModal" title="{{ __('Are you sure you want to delete your account?') }}">
        <p class="text-sm opacity-70">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
        </p>

        <form wire:submit="deleteUser" class="space-y-6 mt-4">
            <x-password wire:model="password" label="{{ __('Password') }}" />

            <x-slot:actions>
                <x-button label="{{ __('Cancel') }}" @click="$wire.showDeleteModal = false" />
                <x-button type="submit" label="{{ __('Delete account') }}" class="btn-error" data-test="confirm-delete-user-button" />
            </x-slot:actions>
        </form>
    </x-modal>
</section>
