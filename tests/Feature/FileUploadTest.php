<?php

use App\Models\Setting;
use App\Models\Share;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('upload page can be rendered', function () {
    $response = $this->get(route('upload'));

    $response->assertOk();
});

test('upload page requires system password when configured', function () {
    Setting::set('system_password', bcrypt('system-secret'));

    $response = $this->get(route('upload'));

    $response->assertRedirect(route('system-password'));
});

test('upload page accessible after system password verified', function () {
    Setting::set('system_password', bcrypt('system-secret'));

    $response = $this->withSession(['system_password_verified' => true])
        ->get(route('upload'));

    $response->assertOk();
});

test('file upload creates share', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('document.pdf', 1024);

    Livewire::test(\App\Livewire\FileUploader::class)
        ->set('files', [$file])
        ->call('createShare')
        ->assertRedirectContains('/share/');

    expect(Share::query()->count())->toBe(1);

    $share = Share::query()->first();
    expect($share->files)->toHaveCount(1);
    expect($share->files->first()->original_name)->toBe('document.pdf');
});

test('file upload with password creates password-protected share', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('secret.txt', 512);

    Livewire::test(\App\Livewire\FileUploader::class)
        ->set('files', [$file])
        ->set('usePassword', true)
        ->set('password', 'my-password')
        ->call('createShare')
        ->assertRedirectContains('/share/');

    $share = Share::query()->first();
    expect($share->isPasswordProtected())->toBeTrue();
});

test('file upload with expiration sets expires_at', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('file.txt', 256);

    Livewire::test(\App\Livewire\FileUploader::class)
        ->set('files', [$file])
        ->set('expiration', '24h')
        ->call('createShare')
        ->assertRedirectContains('/share/');

    $share = Share::query()->first();
    expect($share->expires_at)->not->toBeNull();
});

test('file upload with max downloads sets limit', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('file.txt', 256);

    Livewire::test(\App\Livewire\FileUploader::class)
        ->set('files', [$file])
        ->set('maxDownloads', 5)
        ->call('createShare')
        ->assertRedirectContains('/share/');

    $share = Share::query()->first();
    expect($share->max_downloads)->toBe(5);
});

test('file upload requires at least one file', function () {
    Livewire::test(\App\Livewire\FileUploader::class)
        ->set('files', [])
        ->call('createShare')
        ->assertHasErrors(['files']);
});

test('file upload blocks when storage is full', function () {
    Storage::fake('shares');
    Setting::set('max_storage_quota', 100);
    Share::factory()->create(['total_size' => 100]);

    $file = UploadedFile::fake()->create('file.txt', 1);

    Livewire::test(\App\Livewire\FileUploader::class)
        ->set('files', [$file])
        ->call('createShare')
        ->assertHasErrors(['files']);
});

test('system password prompt verifies correct password', function () {
    Setting::set('system_password', bcrypt('system-secret'));

    Livewire::test(\App\Livewire\SystemPasswordPrompt::class)
        ->set('password', 'system-secret')
        ->call('verify')
        ->assertRedirect(route('upload'));
});

test('system password prompt rejects incorrect password', function () {
    Setting::set('system_password', bcrypt('system-secret'));

    Livewire::test(\App\Livewire\SystemPasswordPrompt::class)
        ->set('password', 'wrong')
        ->call('verify')
        ->assertHasErrors(['password']);
});
