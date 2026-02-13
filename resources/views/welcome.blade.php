<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('mary-theme')?.replaceAll('"','') || 'dark')</script>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Models\Setting::get('site_title') ?: config('app.name', 'SealShare') }}</title>
    @include('partials.head')
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-4xl font-bold mb-2">{{ \App\Models\Setting::get('site_title') ?: config('app.name', 'SealShare') }}</h1>
        <p class="text-base-content/60 mb-6">{{ __('Secure file sharing made simple.') }}</p>
        <a href="{{ route('upload') }}" class="btn btn-primary">{{ __('Upload Files') }}</a>
    </div>
</body>
</html>
