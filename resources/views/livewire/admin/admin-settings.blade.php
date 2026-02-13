<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">{{ __('System Settings') }}</h1>

    @if (session('message'))
        <div class="alert alert-success mb-6">
            <x-icon name="o-check-circle" class="w-5 h-5" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <form wire:submit="saveSettings">
        <x-card title="{{ __('Branding') }}" shadow class="mb-6">
            <div class="space-y-4">
                <x-input
                    wire:model="siteTitle"
                    label="{{ __('Site Title') }}"
                    hint="{{ __('Displayed as the heading on the upload page.') }}"
                />

                <x-textarea
                    wire:model="siteDescription"
                    label="{{ __('Site Description') }}"
                    hint="{{ __('Displayed below the title on the upload page.') }}"
                    rows="3"
                />

                <div>
                    <label class="label label-text font-semibold">{{ __('Logo') }}</label>

                    @if ($currentLogo)
                        <div class="flex items-center gap-4 mb-3">
                            <img src="{{ Storage::disk('public')->url($currentLogo) }}" alt="{{ __('Site Logo') }}" class="h-16 w-auto rounded" />
                            <x-button
                                label="{{ __('Remove Logo') }}"
                                class="btn-sm btn-ghost text-error"
                                wire:click="removeLogo"
                                wire:confirm="{{ __('Remove the logo?') }}"
                            />
                        </div>
                    @endif

                    <input type="file" wire:model="siteLogo" accept="image/*,.svg,.svgz" class="file-input file-input-bordered w-full" />

                    @if ($siteLogo && is_object($siteLogo))
                        <div class="mt-2">
                            @if (str_contains($siteLogo->getMimeType(), 'svg'))
                                <p class="text-sm opacity-60">{{ __('SVG selected: :name', ['name' => $siteLogo->getClientOriginalName()]) }}</p>
                            @else
                                <p class="text-sm opacity-60">{{ __('Preview:') }}</p>
                                <img src="{{ $siteLogo->temporaryUrl() }}" alt="{{ __('Logo preview') }}" class="h-16 w-auto rounded mt-1" />
                            @endif
                        </div>
                    @endif

                    @error('siteLogo')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror

                    <p class="text-xs opacity-50 mt-1">{{ __('Max 2MB. Recommended: PNG or SVG.') }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="{{ __('Upload Protection') }}" shadow class="mb-6">
            <div class="space-y-4">
                <div>
                    <x-password
                        wire:model="systemPassword"
                        label="{{ __('System Upload Password') }}"
                        hint="{{ __('Leave blank to keep current. Set a password to require it before uploading.') }}"
                    />

                    @if ($hasSystemPassword)
                        <div class="mt-2">
                            <x-button
                                label="{{ __('Clear System Password') }}"
                                class="btn-sm btn-ghost text-error"
                                wire:click="clearSystemPassword"
                                wire:confirm="{{ __('Remove the system password?') }}"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card title="{{ __('Upload Limits') }}" shadow class="mb-6">
            <div class="space-y-4">
                <x-toggle
                    wire:model.live="allowNeverExpire"
                    label="{{ __('Allow shares to never expire') }}"
                    hint="{{ __('When disabled, users must select an expiration time.') }}"
                />

                <x-select
                    wire:model="defaultExpiration"
                    label="{{ __('Default Expiration') }}"
                    :placeholder="$allowNeverExpire ? __('None') : null"
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
                    wire:model="maxFileSize"
                    label="{{ __('Max file size (MB)') }}"
                    type="number"
                    min="1"
                    max="{{ $phpMaxUploadMb }}"
                    suffix="MB"
                    hint="{{ __('PHP limit: :max MB (upload_max_filesize / post_max_size)', ['max' => $phpMaxUploadMb]) }}"
                />

                <x-input
                    wire:model="maxFilesPerShare"
                    label="{{ __('Max files per share') }}"
                    type="number"
                    min="1"
                />

                <x-input
                    wire:model="maxSizePerShare"
                    label="{{ __('Max total size per share (GB)') }}"
                    type="number"
                    min="1"
                    suffix="GB"
                />
            </div>
        </x-card>

        <x-card title="{{ __('Storage') }}" shadow class="mb-6">
            <div class="space-y-4">
                <x-input
                    wire:model="maxStorageQuota"
                    label="{{ __('Max storage quota (GB)') }}"
                    type="number"
                    min="1"
                    suffix="GB"
                    hint="{{ __('When reached, new uploads are blocked.') }}"
                />
            </div>
        </x-card>

        <x-button type="submit" label="{{ __('Save Settings') }}" class="btn-primary w-full" icon="o-check" spinner="saveSettings" />
    </form>
</div>
