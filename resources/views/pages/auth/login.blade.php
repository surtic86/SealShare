<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <x-input
                name="email"
                label="{{ __('Email address') }}"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
                icon="o-envelope"
            />

            <!-- Password -->
            <div class="relative">
                <x-password
                    name="password"
                    label="{{ __('Password') }}"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('Password') }}"
                />

                @if (Route::has('password.request'))
                    <a class="absolute top-0 text-sm end-0 link link-primary" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <!-- Remember Me -->
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="checkbox checkbox-sm" {{ old('remember') ? 'checked' : '' }} />
                <span class="text-sm">{{ __('Remember me') }}</span>
            </label>

            <div class="flex items-center justify-end">
                <x-button type="submit" label="{{ __('Log in') }}" class="btn-primary w-full" data-test="login-button" />
            </div>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse opacity-60">
                <span>{{ __('Don\'t have an account?') }}</span>
                <a href="{{ route('register') }}" class="link link-primary" wire:navigate>{{ __('Sign up') }}</a>
            </div>
        @endif
    </div>
</x-layouts::auth>
