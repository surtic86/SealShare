<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('mary-theme')?.replaceAll('"','') || 'dark')</script>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen font-sans antialiased bg-base-200/50 flex flex-col">

        {{-- MAIN CONTENT --}}
        <main class="flex-1 w-full max-w-5xl mx-auto px-4 py-8">
            {{ $slot }}
        </main>

        {{-- FOOTER NAV --}}
        <footer class="border-t border-base-300 bg-base-100/50">
            <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between text-sm">
                <a href="{{ route('upload') }}" class="font-medium opacity-70 hover:opacity-100 transition-opacity">
                    {{ \App\Models\Setting::get('site_title') ?: config('app.name', 'SealShare') }}
                </a>
                <nav class="flex items-center gap-4">
                    <x-theme-toggle class="opacity-60 hover:opacity-100 transition-opacity" />
                    @auth
                        @if(auth()->user()->is_admin)
                            <a href="{{ route('admin.dashboard') }}" class="opacity-60 hover:opacity-100 transition-opacity">{{ __('Dashboard') }}</a>
                            <a href="{{ route('admin.settings') }}" class="opacity-60 hover:opacity-100 transition-opacity">{{ __('Settings') }}</a>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="opacity-60 hover:opacity-100 transition-opacity">{{ __('Profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="opacity-60 hover:opacity-100 transition-opacity">{{ __('Logout') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="opacity-60 hover:opacity-100 transition-opacity">{{ __('Login') }}</a>
                    @endauth
                </nav>
            </div>
        </footer>

        {{-- Toast --}}
        <x-toast />
    </body>
</html>
