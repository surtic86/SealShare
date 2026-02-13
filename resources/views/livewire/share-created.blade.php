<div class="max-w-lg mx-auto">
    <x-card title="{{ __('Share Created!') }}" subtitle="{{ __('Your files are ready to share') }}" shadow>
        <div class="space-y-4">
            {{-- Share URL --}}
            <div
                x-data="{
                    copied: false,
                    url: '{{ route('share.download', $share) }}',
                    async copy() {
                        try {
                            await navigator.clipboard.writeText(this.url);
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        } catch (e) {}
                    }
                }"
            >
                <label class="label font-medium text-sm">{{ __('Share Link') }}</label>
                <div class="join w-full">
                    <input
                        type="text"
                        readonly
                        :value="url"
                        class="input input-bordered join-item w-full"
                    />
                    <button type="button" @click="copy()" class="btn join-item">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" class="text-success">{{ __('Copied!') }}</span>
                    </button>
                </div>
            </div>

            {{-- Details --}}
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-base-200 rounded-lg p-3">
                    <span class="opacity-60">{{ __('Files') }}</span>
                    <p class="font-semibold">{{ $share->files->count() }}</p>
                </div>
                <div class="bg-base-200 rounded-lg p-3">
                    <span class="opacity-60">{{ __('Total Size') }}</span>
                    <p class="font-semibold">{{ Number::fileSize($share->total_size) }}</p>
                </div>
                <div class="bg-base-200 rounded-lg p-3">
                    <span class="opacity-60">{{ __('Expires') }}</span>
                    <p class="font-semibold">{{ $share->expires_at ? $share->expires_at->diffForHumans() : __('Never') }}</p>
                </div>
                <div class="bg-base-200 rounded-lg p-3">
                    <span class="opacity-60">{{ __('Max Downloads') }}</span>
                    <p class="font-semibold">{{ $share->max_downloads ?? __('Unlimited') }}</p>
                </div>
            </div>

            @if ($share->isPasswordProtected())
                <div class="alert alert-info">
                    <x-icon name="o-lock-closed" class="w-5 h-5" />
                    <span>{{ __('This share is password protected') }}</span>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Upload More') }}" link="{{ route('upload') }}" icon="o-plus" />
        </x-slot:actions>
    </x-card>
</div>
