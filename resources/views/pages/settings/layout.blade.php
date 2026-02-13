<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <x-menu class="!p-0">
            <x-menu-item title="{{ __('Profile') }}" link="{{ route('profile.edit') }}" wire:navigate />
            <x-menu-item title="{{ __('Password') }}" link="{{ route('user-password.edit') }}" wire:navigate />
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <x-menu-item title="{{ __('Two-Factor Auth') }}" link="{{ route('two-factor.show') }}" wire:navigate />
            @endif
            <x-menu-item title="{{ __('Appearance') }}" link="{{ route('appearance.edit') }}" wire:navigate />
        </x-menu>
    </div>

    <div class="divider md:hidden"></div>

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="text-lg font-semibold">{{ $heading ?? '' }}</h2>
        <p class="text-sm opacity-60">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
