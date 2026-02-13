<?php

use App\Livewire\FileUploader;
use App\Livewire\ShareDownload;
use App\Models\Share;
use App\Models\User;
use App\Services\FileEncryptionService;
use App\Services\ShareService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// --- Session stores derived key, not plaintext password ---

test('session stores derived encryption key instead of plaintext password', function () {
    Storage::fake('shares');

    $service = app(ShareService::class);
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => 'test-password-secure',
    ]);

    Livewire::test(ShareDownload::class, ['share' => $share])
        ->set('password', 'test-password-secure')
        ->call('verifyPassword');

    expect(session('share_key_'.$share->token))->not->toBeNull();
    expect(session('share_key_'.$share->token))->not->toBe('test-password-secure');
    expect(strlen(session('share_key_'.$share->token)))->toBe(64);
});

// --- Rate limiting on password verification ---

test('rate limiting blocks after 5 failed password attempts', function () {
    Storage::fake('shares');

    $service = app(ShareService::class);
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => 'correct-password',
    ]);

    $component = Livewire::test(ShareDownload::class, ['share' => $share]);

    for ($i = 0; $i < 5; $i++) {
        $component->set('password', 'wrong-password')
            ->call('verifyPassword')
            ->assertHasErrors(['password']);
    }

    $component->set('password', 'correct-password')
        ->call('verifyPassword')
        ->assertHasErrors(['password'])
        ->assertSet('authenticated', false);
});

test('rate limiter clears after successful password verification', function () {
    Storage::fake('shares');

    $service = app(ShareService::class);
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => 'correct-password',
    ]);

    $component = Livewire::test(ShareDownload::class, ['share' => $share]);

    $component->set('password', 'wrong-password')
        ->call('verifyPassword')
        ->assertHasErrors(['password']);

    $component->set('password', 'correct-password')
        ->call('verifyPassword')
        ->assertSet('authenticated', true);

    $rateLimitKey = 'share-password:'.$share->token.'|127.0.0.1';
    expect(RateLimiter::remaining($rateLimitKey, 5))->toBe(5);
});

// --- Share password minimum length ---

test('share password must be at least 8 characters', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('file.txt', 100);

    Livewire::test(FileUploader::class)
        ->set('files', [$file])
        ->set('usePassword', true)
        ->set('password', 'short')
        ->call('createShare')
        ->assertHasErrors(['password']);
});

test('share password of 8 characters is accepted', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('file.txt', 100);

    Livewire::test(FileUploader::class)
        ->set('files', [$file])
        ->set('usePassword', true)
        ->set('password', 'longenough')
        ->call('createShare')
        ->assertHasNoErrors(['password']);
});

// --- is_admin not mass assignable ---

test('is_admin is not mass assignable on User model', function () {
    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'mass-assign-test@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    expect($user->is_admin)->toBeFalsy();
});

// --- Setup wizard guard prevents duplicate admins ---

test('setup wizard createAdmin is blocked when admin already exists', function () {
    $adminCountBefore = User::query()->where('is_admin', true)->count();

    $this->post(route('setup'), [
        'name' => 'Second Admin',
        'email' => 'second-admin@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    expect(User::query()->where('email', 'second-admin@example.com')->exists())->toBeFalse();
    expect(User::query()->where('is_admin', true)->count())->toBe($adminCountBefore);
});

// --- Content-Disposition sanitization ---

test('content disposition handles special characters in filename', function () {
    Storage::fake('shares');

    $service = app(ShareService::class);
    $encryptionService = app(FileEncryptionService::class);

    $file = UploadedFile::fake()->create('normal.txt', 100);

    $share = $service->createShare([
        ['file' => $file, 'relativePath' => null],
    ]);

    $share->load('files');
    $shareFile = $share->files->first();

    $shareFile->original_name = 'file"with"quotes.txt';
    $shareFile->save();

    $encryptedDir = Storage::disk('shares')->path($share->token);
    if (! is_dir($encryptedDir)) {
        mkdir($encryptedDir, 0755, true);
    }
    $encryptedPath = $encryptedDir.'/'.basename($shareFile->stored_path);
    $tempSource = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempSource, 'test content');
    $encryptionService->encryptFile($tempSource, $encryptedPath, $share->encryption_key);
    unlink($tempSource);

    $response = $encryptionService->decryptFileStream(
        $encryptedPath,
        $share->encryption_key,
        'file"with"quotes.txt',
        'text/plain',
        12,
    );

    $contentDisposition = $response->headers->get('Content-Disposition');
    expect($contentDisposition)->not->toContain('file"with"quotes.txt');
    expect($contentDisposition)->toContain('attachment');
});

// --- SVG upload rejected ---

test('svg upload is rejected for site logo', function () {
    $admin = User::query()->where('is_admin', true)->first();

    $phpMaxMb = \App\Livewire\Admin\AdminSettings::phpMaxUploadMb();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\AdminSettings::class)
        ->set('maxFileSize', $phpMaxMb)
        ->set('siteLogo', UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'))
        ->call('saveSettings')
        ->assertHasErrors(['siteLogo']);
});

// --- Relative path validation (Zip Slip prevention) ---

test('relative paths with directory traversal are sanitized', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('file.txt', 100);

    Livewire::test(FileUploader::class)
        ->set('files', [$file])
        ->set('relativePaths', ['../../etc/passwd'])
        ->call('createShare')
        ->assertRedirectContains('/share/');

    $share = Share::query()->first();
    $shareFile = $share->files->first();
    expect($shareFile->relative_path)->toBeNull();
});

test('relative paths with absolute paths are sanitized', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('file.txt', 100);

    Livewire::test(FileUploader::class)
        ->set('files', [$file])
        ->set('relativePaths', ['/etc/passwd'])
        ->call('createShare')
        ->assertRedirectContains('/share/');

    $share = Share::query()->first();
    $shareFile = $share->files->first();
    expect($shareFile->relative_path)->toBeNull();
});

test('valid relative paths are preserved', function () {
    Storage::fake('shares');

    $file = UploadedFile::fake()->create('file.txt', 100);

    Livewire::test(FileUploader::class)
        ->set('files', [$file])
        ->set('relativePaths', ['folder/subfolder/file.txt'])
        ->call('createShare')
        ->assertRedirectContains('/share/');

    $share = Share::query()->first();
    $shareFile = $share->files->first();
    expect($shareFile->relative_path)->toBe('folder/subfolder/file.txt');
});

// --- Security headers ---

test('security headers are present on responses', function () {
    $response = $this->get(route('upload'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

// --- Token collision retry ---

test('share service generates unique tokens', function () {
    Storage::fake('shares');

    $service = app(ShareService::class);

    $shares = [];
    for ($i = 0; $i < 5; $i++) {
        $file = UploadedFile::fake()->create("file{$i}.txt", 100);
        $shares[] = $service->createShare([
            ['file' => $file, 'relativePath' => null],
        ]);
    }

    $tokens = array_map(fn ($s) => $s->token, $shares);
    expect(array_unique($tokens))->toHaveCount(5);
});
