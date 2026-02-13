<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Setup SealShare')" :description="__('Create your admin account to get started')" />

    <form wire:submit="createAdmin" class="flex flex-col gap-6">
        <x-input
            wire:model="name"
            label="{{ __('Name') }}"
            type="text"
            required
            autofocus
            placeholder="{{ __('Admin name') }}"
            icon="o-user"
        />

        <x-input
            wire:model="email"
            label="{{ __('Email address') }}"
            type="email"
            required
            placeholder="admin@example.com"
            icon="o-envelope"
        />

        <x-password
            wire:model="password"
            label="{{ __('Password') }}"
            required
            placeholder="{{ __('Password') }}"
        />

        <x-password
            wire:model="password_confirmation"
            label="{{ __('Confirm password') }}"
            required
            placeholder="{{ __('Confirm password') }}"
        />

        <x-button type="submit" label="{{ __('Create Admin Account') }}" class="btn-primary w-full" spinner="createAdmin" />
    </form>
</div>
