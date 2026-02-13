<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'flex items-center gap-2 font-semibold']) }} wire:navigate>
    <x-app-logo-icon class="size-6 fill-current" />
    <span>{{ \App\Models\Setting::get('site_title') ?: config('app.name', 'SealShare') }}</span>
</a>
