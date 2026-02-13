<?php

namespace App\Livewire\Admin;

use App\Models\Share;
use App\Models\ShareFile;
use App\Services\ShareService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AdminDashboard extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public function deleteShare(int $shareId, ShareService $shareService): void
    {
        $share = Share::query()->findOrFail($shareId);
        $shareService->deleteShare($share);
    }

    /**
     * @return array<string, array<string, string|bool>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'token', 'label' => __('Token')],
            ['key' => 'files_count', 'label' => __('Files')],
            ['key' => 'total_size', 'label' => __('Size')],
            ['key' => 'download_count', 'label' => __('Downloads')],
            ['key' => 'expires_at', 'label' => __('Expires')],
            ['key' => 'created_at', 'label' => __('Created')],
        ];
    }

    public function render(): mixed
    {
        $shareService = app(ShareService::class);

        $shares = Share::query()
            ->withCount('files')
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);

        return view('livewire.admin.admin-dashboard', [
            'shares' => $shares,
            'totalShares' => Share::query()->count(),
            'activeShares' => Share::query()->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->count(),
            'totalFiles' => ShareFile::query()->count(),
            'usedSpace' => $shareService->getTotalUsedSpace(),
            'maxQuota' => $shareService->getMaxStorageQuota(),
            'headers' => $this->headers(),
        ]);
    }
}
