<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-password
                name="password"
                label="{{ __('Password') }}"
                required
                autocomplete="current-password"
                placeholder="{{ __('Password') }}"
            />

            <x-button type="submit" label="{{ __('Confirm') }}" class="btn-primary w-full" data-test="confirm-password-button" />
        </form>
    </div>
</x-layouts::auth>
