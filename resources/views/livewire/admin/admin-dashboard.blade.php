<div>
    <h1 class="text-2xl font-bold mb-6">{{ __('Admin Dashboard') }}</h1>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-4">
                <p class="text-sm opacity-60">{{ __('Total Shares') }}</p>
                <p class="text-2xl font-bold">{{ $totalShares }}</p>
            </div>
        </div>
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-4">
                <p class="text-sm opacity-60">{{ __('Active Shares') }}</p>
                <p class="text-2xl font-bold">{{ $activeShares }}</p>
            </div>
        </div>
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-4">
                <p class="text-sm opacity-60">{{ __('Total Files') }}</p>
                <p class="text-2xl font-bold">{{ $totalFiles }}</p>
            </div>
        </div>
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-4">
                <p class="text-sm opacity-60">{{ __('Disk Usage') }}</p>
                <p class="text-2xl font-bold">{{ Number::fileSize($usedSpace) }}</p>
                <progress class="progress progress-primary w-full mt-1" value="{{ $maxQuota > 0 ? ($usedSpace / $maxQuota) * 100 : 0 }}" max="100"></progress>
                <p class="text-xs opacity-50">{{ Number::fileSize($usedSpace) }} / {{ Number::fileSize($maxQuota) }}</p>
            </div>
        </div>
    </div>

    {{-- Shares Table --}}
    <x-card title="{{ __('All Shares') }}" shadow>
        <x-table :headers="$headers" :rows="$shares" :sort-by="$sortBy" with-pagination>
            @scope('cell_total_size', $share)
                {{ Number::fileSize($share->total_size) }}
            @endscope

            @scope('cell_expires_at', $share)
                @if ($share->expires_at)
                    <span class="{{ $share->isExpired() ? 'text-error' : '' }}">
                        {{ $share->expires_at->diffForHumans() }}
                    </span>
                @else
                    <span class="opacity-50">{{ __('Never') }}</span>
                @endif
            @endscope

            @scope('cell_created_at', $share)
                {{ $share->created_at->diffForHumans() }}
            @endscope

            @scope('actions', $share)
                <div class="flex gap-1">
                    <a href="{{ route('share.download', $share) }}" class="btn btn-ghost btn-xs" target="_blank">
                        <x-icon name="o-eye" class="w-4 h-4" />
                    </a>
                    <x-button
                        icon="o-trash"
                        class="btn-ghost btn-xs text-error"
                        wire:click="deleteShare({{ $share->id }})"
                        wire:confirm="{{ __('Are you sure you want to delete this share?') }}"
                    />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
