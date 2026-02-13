<?php

use App\Models\Share;
use Illuminate\Support\Facades\Storage;

test('cleanup removes expired shares', function () {
    Storage::fake('shares');

    $expired = Share::factory()->expired()->create();
    $active = Share::factory()->expiresInHours(24)->create();
    $noExpiry = Share::factory()->create(['expires_at' => null]);

    $this->artisan('shares:cleanup')
        ->expectsOutputToContain('Cleaned up 1 expired share(s)')
        ->assertExitCode(0);

    expect(Share::query()->find($expired->id))->toBeNull();
    expect(Share::query()->find($active->id))->not->toBeNull();
    expect(Share::query()->find($noExpiry->id))->not->toBeNull();
});

test('cleanup removes shares that reached download limit', function () {
    Storage::fake('shares');

    $reachedLimit = Share::factory()->withMaxDownloads(5)->create(['download_count' => 5]);
    $underLimit = Share::factory()->withMaxDownloads(5)->create(['download_count' => 3]);

    $this->artisan('shares:cleanup')
        ->expectsOutputToContain('Cleaned up 1 expired share(s)')
        ->assertExitCode(0);

    expect(Share::query()->find($reachedLimit->id))->toBeNull();
    expect(Share::query()->find($underLimit->id))->not->toBeNull();
});

test('cleanup handles no expired shares', function () {
    Storage::fake('shares');

    Share::factory()->create(['expires_at' => null]);

    $this->artisan('shares:cleanup')
        ->expectsOutputToContain('Cleaned up 0 expired share(s)')
        ->assertExitCode(0);
});

test('cleanup removes both expired and download-limited shares', function () {
    Storage::fake('shares');

    $expired = Share::factory()->expired()->create();
    $limitReached = Share::factory()->withMaxDownloads(1)->create(['download_count' => 1]);
    $active = Share::factory()->create(['expires_at' => null]);

    $this->artisan('shares:cleanup')
        ->expectsOutputToContain('Cleaned up 2 expired share(s)')
        ->assertExitCode(0);

    expect(Share::query()->find($expired->id))->toBeNull();
    expect(Share::query()->find($limitReached->id))->toBeNull();
    expect(Share::query()->find($active->id))->not->toBeNull();
});
