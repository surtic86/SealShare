<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('mary-theme')?.replaceAll('"','') || 'dark')</script>
    <head>
        @include('partials.head')
        @livewireStyles
    </head>
    <body class="min-h-screen bg-base-200 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current" />
                    </span>
                    <span class="sr-only">{{ \App\Models\Setting::get('site_title') ?: config('app.name', 'SealShare') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
                <div class="flex justify-center">
                    <x-theme-toggle class="opacity-60 hover:opacity-100 transition-opacity" />
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
