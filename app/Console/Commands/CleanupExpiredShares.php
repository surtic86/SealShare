<?php

namespace App\Console\Commands;

use App\Models\Share;
use App\Services\ShareService;
use Illuminate\Console\Command;

class CleanupExpiredShares extends Command
{
    protected $signature = 'shares:cleanup';

    protected $description = 'Delete expired shares and shares that have reached their download limit';

    public function handle(ShareService $shareService): int
    {
        $expiredShares = Share::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now())
                    ->orWhereRaw('max_downloads IS NOT NULL AND download_count >= max_downloads');
            })
            ->get();

        $count = $expiredShares->count();

        foreach ($expiredShares as $share) {
            $shareService->deleteShare($share);
        }

        $this->info("Cleaned up {$count} expired share(s).");

        return self::SUCCESS;
    }
}
