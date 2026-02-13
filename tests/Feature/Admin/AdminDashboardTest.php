<?php

use App\Models\Share;
use App\Models\ShareFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('admin dashboard requires authentication', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
});

test('non-admin user cannot access admin dashboard', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertForbidden();
});

test('admin can access dashboard', function () {
    $admin = User::query()->where('is_admin', true)->first();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
});

test('admin dashboard shows stats', function () {
    $admin = User::query()->where('is_admin', true)->first();

    $share = Share::factory()->create(['total_size' => 1024]);
    ShareFile::factory()->create(['share_id' => $share->id]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Total Shares');
    $response->assertSee('Active Shares');
    $response->assertSee('Total Files');
    $response->assertSee('Disk Usage');
});

test('admin can delete share', function () {
    Storage::fake('shares');

    $admin = User::query()->where('is_admin', true)->first();

    $share = Share::factory()->create();
    $shareId = $share->id;

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\AdminDashboard::class)
        ->call('deleteShare', $shareId);

    expect(Share::query()->find($shareId))->toBeNull();
});

test('admin dashboard shows shares table', function () {
    $admin = User::query()->where('is_admin', true)->first();

    $share = Share::factory()->create(['token' => 'testtoken12345678']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('testtoken12345678');
});
