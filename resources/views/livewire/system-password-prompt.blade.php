<div class="flex flex-col gap-6">
    <x-auth-header :title="__('System Password Required')" :description="__('Enter the system password to access the upload page')" />

    <form wire:submit="verify" class="flex flex-col gap-6">
        <x-password
            wire:model="password"
            label="{{ __('Password') }}"
            required
            placeholder="{{ __('System password') }}"
        />

        <x-button type="submit" label="{{ __('Continue') }}" class="btn-primary w-full" spinner="verify" />
    </form>
</div>
