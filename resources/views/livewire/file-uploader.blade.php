<div class="max-w-3xl mx-auto">
    <div class="text-center mb-6">
        @if ($siteLogo)
            <img src="{{ Storage::disk('public')->url($siteLogo) }}" alt="{{ $siteTitle ?: config('app.name', 'SealShare') }}" class="h-20 w-auto mx-auto mb-4" />
        @else
            <x-app-logo-icon class="h-16 w-16 mx-auto mb-4" />
        @endif

        <h1 class="text-2xl font-bold">{{ $siteTitle ?: config('app.name', 'SealShare') }}</h1>

        <p class="mt-2 opacity-70">{{ $siteDescription ?: __('Share your files safely and securely') }}</p>
    </div>

    @if ($isStorageFull)
        <div class="alert alert-warning mb-6">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
            <span>{{ __('Storage is full. Uploads are temporarily disabled.') }}</span>
        </div>
    @else
        <form
            wire:submit="createShare"
            x-data="{
                uploading: false,
                progress: 0,
                dragging: false,
                handleDrop(e) {
                    this.dragging = false;
                    const items = e.dataTransfer.items;
                    const files = [];

                    for (let i = 0; i < items.length; i++) {
                        const entry = items[i].webkitGetAsEntry?.();
                        if (entry) {
                            this.traverseEntry(entry, '', files);
                        } else if (items[i].kind === 'file') {
                            files.push({ file: items[i].getAsFile(), path: null });
                        }
                    }

                    setTimeout(() => {
                        const dt = new DataTransfer();
                        const paths = [];
                        files.forEach(f => {
                            dt.items.add(f.file);
                            paths.push(f.path);
                        });
                        $wire.relativePaths = paths;
                        $wire.uploadMultiple('files', dt.files);
                    }, 500);
                },
                traverseEntry(entry, path, files) {
                    if (entry.isFile) {
                        entry.file(file => {
                            files.push({ file, path: path ? path + '/' + file.name : null });
                        });
                    } else if (entry.isDirectory) {
                        const reader = entry.createReader();
                        reader.readEntries(entries => {
                            entries.forEach(e => this.traverseEntry(e, path ? path + '/' + entry.name : entry.name, files));
                        });
                    }
                }
            }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-finish="progress = 100"
            x-on:livewire-upload-cancel="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
        >
            {{-- Drop Zone --}}
            <div
                class="border-2 border-dashed rounded-xl p-8 text-center transition-colors mb-6"
                :class="{
                    'border-primary bg-primary/5': dragging,
                    'border-base-300 hover:border-primary/50': !dragging,
                    'opacity-50 pointer-events-none': uploading
                }"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="handleDrop($event)"
            >
                <x-icon name="o-cloud-arrow-up" class="w-12 h-12 mx-auto opacity-40 mb-3" />
                <p class="font-medium">{{ __('Drag & drop files or folders here') }}</p>
                <p class="text-sm opacity-60 mt-1">{{ __('or click to browse') }}</p>

                <label class="btn btn-outline btn-sm mt-4 cursor-pointer" :class="uploading && 'btn-disabled'">
                    {{ __('Browse Files') }}
                    <input
                        type="file"
                        wire:model="files"
                        multiple
                        class="hidden"
                        :disabled="uploading"
                    />
                </label>
            </div>

            {{-- Upload Progress --}}
            <div x-show="uploading" x-cloak class="mb-6">
                <template x-if="progress < 100">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">{{ __('Uploading...') }} <span x-text="Math.round(progress)"></span>%</span>
                            <button type="button" class="btn btn-ghost btn-xs" x-on:click="$wire.cancelUpload('files')">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                        <progress class="progress progress-primary w-full" max="100" x-bind:value="progress"></progress>
                    </div>
                </template>
                <template x-if="progress >= 100">
                    <div class="flex items-center gap-3 text-sm font-medium">
                        <span class="loading loading-spinner loading-sm"></span>
                        {{ __('Processing files...') }}
                    </div>
                </template>
            </div>

            {{-- Errors --}}
            @error('files')
                <div x-init="uploading = false" class="alert alert-error mb-4">{{ $message }}</div>
            @enderror

            {{-- File List --}}
            @if (count($files))
                <div x-init="uploading = false" class="mb-6">
                    <h3 class="font-semibold mb-2">{{ __('Selected Files') }} ({{ count($files) }})</h3>
                    <div class="space-y-1 max-h-60 overflow-y-auto">
                        @foreach ($files as $index => $file)
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-base-200 text-sm">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-icon name="o-document" class="w-4 h-4 flex-shrink-0" />
                                    <span class="truncate">
                                        {{ $relativePaths[$index] ?? $file->getClientOriginalName() }}
                                    </span>
                                    <span class="opacity-50 flex-shrink-0">
                                        ({{ Number::fileSize($file->getSize()) }})
                                    </span>
                                </div>
                                <button type="button" wire:click="removeFile({{ $index }})" class="btn btn-ghost btn-xs">
                                    <x-icon name="o-x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Options --}}
            <x-card title="{{ __('Share Options') }}" class="mb-6" shadow>
                <div class="space-y-4">
                    <x-toggle wire:model.live="usePassword" label="{{ __('Password protect') }}" />

                    @if ($usePassword)
                        <x-password wire:model="password" label="{{ __('Password') }}" />
                    @endif

                    <x-select
                        wire:model="expiration"
                        label="{{ __('Expiration') }}"
                        :placeholder="$allowNeverExpire ? __('Never') : null"
                        :options="[
                            ['id' => '1h', 'name' => __('1 Hour')],
                            ['id' => '24h', 'name' => __('24 Hours')],
                            ['id' => '48h', 'name' => __('48 Hours')],
                            ['id' => '7d', 'name' => __('7 Days')],
                            ['id' => '14d', 'name' => __('14 Days')],
                            ['id' => '30d', 'name' => __('30 Days')],
                        ]"
                    />

                    <x-input
                        wire:model="maxDownloads"
                        label="{{ __('Max downloads') }}"
                        type="number"
                        min="1"
                        placeholder="{{ __('Unlimited') }}"
                    />
                </div>
            </x-card>

            {{-- Submit --}}
            <x-button
                type="submit"
                label="{{ __('Create Share Link') }}"
                class="btn-primary w-full"
                icon="o-link"
                spinner="createShare"
                x-bind:disabled="uploading || {{ count($files) === 0 ? 'true' : 'false' }}"
            />
        </form>
    @endif
</div>
