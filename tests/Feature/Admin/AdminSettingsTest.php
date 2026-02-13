<?php

use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

test('admin settings requires authentication', function () {
    $response = $this->get(route('admin.settings'));

    $response->assertRedirect(route('login'));
});

test('non-admin user cannot access settings', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get(route('admin.settings'));

    $response->assertForbidden();
});

test('admin can access settings page', function () {
    $admin = User::query()->where('is_admin', true)->first();

    $response = $this->actingAs($admin)->get(route('admin.settings'));

    $response->assertOk();
});

test('admin can save settings', function () {
    $admin = User::query()->where('is_admin', true)->first();

    $phpMaxMb = \App\Livewire\Admin\AdminSettings::phpMaxUploadMb();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\AdminSettings::class)
        ->set('maxFileSize', min(200, $phpMaxMb))
        ->set('maxStorageQuota', 50)
        ->set('maxFilesPerShare', 100)
        ->set('maxSizePerShare', 5)
        ->set('defaultExpiration', '7d')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect(Setting::get('max_file_size'))->toBe((string) (min(200, $phpMaxMb) * 1024 * 1024));
    expect(Setting::get('max_storage_quota'))->toBe((string) (50 * 1024 * 1024 * 1024));
    expect(Setting::get('max_files_per_share'))->toBe('100');
    expect(Setting::get('max_size_per_share'))->toBe((string) (5 * 1024 * 1024 * 1024));
    expect(Setting::get('default_expiration'))->toBe('7d');
});

test('admin can set system password', function () {
    $admin = User::query()->where('is_admin', true)->first();
    $phpMaxMb = \App\Livewire\Admin\AdminSettings::phpMaxUploadMb();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\AdminSettings::class)
        ->set('maxFileSize', $phpMaxMb)
        ->set('systemPassword', 'new-system-password')
        ->call('saveSettings')
        ->assertHasNoErrors();

    $storedPassword = Setting::get('system_password');
    expect($storedPassword)->not->toBeNull();
    expect(\Illuminate\Support\Facades\Hash::check('new-system-password', $storedPassword))->toBeTrue();
});

test('admin can clear system password', function () {
    $admin = User::query()->where('is_admin', true)->first();

    Setting::set('system_password', bcrypt('existing-password'));

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\AdminSettings::class)
        ->call('clearSystemPassword')
        ->assertHasNoErrors();

    expect(Setting::get('system_password'))->toBeNull();
});

test('settings page loads existing values', function () {
    $admin = User::query()->where('is_admin', true)->first();
    $phpMaxMb = \App\Livewire\Admin\AdminSettings::phpMaxUploadMb();
    $testSize = min(40, $phpMaxMb);

    Setting::set('max_file_size', $testSize * 1024 * 1024);
    Setting::set('max_files_per_share', 75);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\AdminSettings::class)
        ->assertSet('maxFileSize', $testSize)
        ->assertSet('maxFilesPerShare', 75);
});

test('settings validation rejects invalid values', function () {
    $admin = User::query()->where('is_admin', true)->first();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\AdminSettings::class)
        ->set('maxFileSize', 0)
        ->set('maxStorageQuota', 0)
        ->call('saveSettings')
        ->assertHasErrors(['maxFileSize', 'maxStorageQuota']);
});
