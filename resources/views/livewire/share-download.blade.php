<div class="w-full max-w-lg mx-auto">
    <div class="text-center mb-6">
        @if ($siteLogo)
            <img src="{{ Storage::disk('public')->url($siteLogo) }}" alt="{{ $siteTitle ?: config('app.name', 'SealShare') }}" class="h-20 w-auto mx-auto mb-4" />
        @else
            <x-app-logo-icon class="h-16 w-16 mx-auto mb-4" />
        @endif

        <h1 class="text-2xl font-bold">{{ $siteTitle ?: config('app.name', 'SealShare') }}</h1>

        <p class="mt-2 opacity-70">{{ $siteDescription ?: __('Share your files safely and securely') }}</p>
    </div>

    @if (! $authenticated)
        {{-- Password form --}}
        <form wire:submit="verifyPassword">
            <x-card title="{{ __('Password Required') }}" subtitle="{{ __('Enter the password to access these files') }}" shadow>
                <x-password
                    wire:model="password"
                    label="{{ __('Password') }}"
                    required
                    placeholder="{{ __('Enter share password') }}"
                />

                <x-slot:actions>
                    <x-button type="submit" label="{{ __('Unlock') }}" class="btn-primary w-full mt-4" icon="o-lock-open" spinner="verifyPassword" />
                </x-slot:actions>
            </x-card>
        </form>
    @else
        {{-- File list --}}
        <x-card title="{{ __('Shared Files') }}" shadow>
            <div class="space-y-2 mb-4">
                @foreach ($share->files as $file)
                    <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-base-200 text-sm">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-icon name="o-document" class="w-4 h-4 flex-shrink-0" />
                            <span class="truncate">{{ $file->relative_path ?: $file->original_name }}</span>
                            <span class="opacity-50 flex-shrink-0">({{ Number::fileSize($file->file_size) }})</span>
                        </div>
                        <a href="{{ route('share.download.file', [$share, $file]) }}" class="btn btn-ghost btn-xs">
                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                        </a>
                    </div>
                @endforeach
            </div>

            @if ($share->expires_at)
                <p class="text-xs opacity-50 mb-2">
                    {{ __('Expires') }}: {{ $share->expires_at->diffForHumans() }}
                </p>
            @endif

            <x-slot:actions>
                @if ($share->files->count() > 1)
                    <a href="{{ route('share.download.all', $share) }}" class="btn btn-primary w-full">
                        <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                        {{ __('Download All as ZIP') }}
                    </a>
                @else
                    <a href="{{ route('share.download.file', [$share, $share->files->first()]) }}" class="btn btn-primary w-full">
                        <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                        {{ __('Download') }}
                    </a>
                @endif
            </x-slot:actions>
        </x-card>
    @endif
</div>
