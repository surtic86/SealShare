<?php

use App\Models\Setting;
use App\Models\Share;
use App\Models\ShareFile;
use App\Services\ShareService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('shares');
    $this->service = app(ShareService::class);
});

test('create share without password stores encryption key', function () {
    $file = UploadedFile::fake()->create('document.pdf', 1024);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ]);

    expect($share)->toBeInstanceOf(Share::class);
    expect($share->token)->toHaveLength(16);
    expect($share->password)->toBeNull();
    expect($share->encryption_key)->not->toBeNull();
    expect($share->encryption_salt)->not->toBeNull();
    expect($share->files)->toHaveCount(1);
    expect($share->files->first()->original_name)->toBe('document.pdf');
});

test('create share with password does not store encryption key', function () {
    $file = UploadedFile::fake()->create('secret.txt', 512);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => 'my-password',
    ]);

    expect($share->password)->not->toBeNull();
    expect($share->encryption_key)->toBeNull();
    expect(\Illuminate\Support\Facades\Hash::check('my-password', $share->password))->toBeTrue();
});

test('create share with options sets expiration and max downloads', function () {
    $file = UploadedFile::fake()->create('file.txt', 256);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'expires_at' => now()->addDay(),
        'max_downloads' => 5,
    ]);

    expect($share->expires_at)->not->toBeNull();
    expect($share->max_downloads)->toBe(5);
});

test('create share with multiple files', function () {
    $file1 = UploadedFile::fake()->create('file1.txt', 100);
    $file2 = UploadedFile::fake()->create('file2.txt', 200);

    $share = $this->service->createShare([
        ['file' => $file1, 'relativePath' => 'folder/file1.txt'],
        ['file' => $file2, 'relativePath' => 'folder/file2.txt'],
    ]);

    expect($share->files)->toHaveCount(2);
    expect($share->files->first()->relative_path)->toBe('folder/file1.txt');
});

test('delete share removes files and database records', function () {
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ]);

    $shareId = $share->id;
    $token = $share->token;

    $this->service->deleteShare($share);

    expect(Share::query()->find($shareId))->toBeNull();
    expect(ShareFile::query()->where('share_id', $shareId)->count())->toBe(0);
    expect(Storage::disk('shares')->directories())->not->toContain($token);
});

test('verify password returns true for correct password', function () {
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => 'correct-password',
    ]);

    expect($this->service->verifyPassword($share, 'correct-password'))->toBeTrue();
    expect($this->service->verifyPassword($share, 'wrong-password'))->toBeFalse();
});

test('verify password returns true for non-password share', function () {
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ]);

    expect($this->service->verifyPassword($share, 'any'))->toBeTrue();
});

test('record download increments counter', function () {
    $share = Share::factory()->create(['download_count' => 0]);

    $this->service->recordDownload($share);

    expect($share->fresh()->download_count)->toBe(1);
});

test('record download auto-deletes when limit reached', function () {
    $share = Share::factory()->withMaxDownloads(1)->create(['download_count' => 0]);

    $this->service->recordDownload($share);

    expect(Share::query()->find($share->id))->toBeNull();
});

test('get total used space sums share sizes', function () {
    Share::factory()->create(['total_size' => 1000]);
    Share::factory()->create(['total_size' => 2000]);

    expect($this->service->getTotalUsedSpace())->toBe(3000);
});

test('is storage full checks against quota', function () {
    Setting::set('max_storage_quota', 1000);

    Share::factory()->create(['total_size' => 999]);
    expect($this->service->isStorageFull())->toBeFalse();

    Share::factory()->create(['total_size' => 1]);
    expect($this->service->isStorageFull())->toBeTrue();
});

test('get decryption key returns stored key for non-password share', function () {
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ]);

    $key = $this->service->getDecryptionKey($share);

    expect($key)->not->toBeNull();
    expect(strlen($key))->toBe(64);
});

test('get decryption key derives key for password share', function () {
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => 'test-password',
    ]);

    $key = $this->service->getDecryptionKey($share, 'test-password');

    expect($key)->not->toBeNull();
    expect(strlen($key))->toBe(64);
});

test('get decryption key throws for password share without password', function () {
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $this->service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => 'test-password',
    ]);

    $this->service->getDecryptionKey($share);
})->throws(RuntimeException::class, 'Password required');
