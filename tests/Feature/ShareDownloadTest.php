<?php

use App\Models\Share;
use App\Services\FileEncryptionService;
use App\Services\ShareService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('share download page renders for valid share', function () {
    Storage::fake('shares');

    $share = createShareWithFile();

    $response = $this->get(route('share.download', $share));

    $response->assertOk();
});

test('share download page returns 404 for expired share', function () {
    $share = Share::factory()->expired()->create();

    $response = $this->get(route('share.download', $share));

    $response->assertNotFound();
});

test('share download page returns 404 when download limit reached', function () {
    $share = Share::factory()->withMaxDownloads(1)->create(['download_count' => 1]);

    $response = $this->get(route('share.download', $share));

    $response->assertNotFound();
});

test('share download page shows password form for password-protected share', function () {
    Storage::fake('shares');

    $share = createShareWithFile('secret-pass');

    $response = $this->get(route('share.download', $share));

    $response->assertOk();
    $response->assertSee('password');
});

test('password verification works for protected share', function () {
    Storage::fake('shares');

    $share = createShareWithFile('my-password');

    Livewire::test(\App\Livewire\ShareDownload::class, ['share' => $share])
        ->assertSet('authenticated', false)
        ->set('password', 'my-password')
        ->call('verifyPassword')
        ->assertSet('authenticated', true)
        ->assertHasNoErrors();
});

test('wrong password is rejected', function () {
    Storage::fake('shares');

    $share = createShareWithFile('my-password');

    Livewire::test(\App\Livewire\ShareDownload::class, ['share' => $share])
        ->set('password', 'wrong-password')
        ->call('verifyPassword')
        ->assertSet('authenticated', false)
        ->assertHasErrors(['password']);
});

test('non-password share shows files directly', function () {
    Storage::fake('shares');

    $share = createShareWithFile();

    Livewire::test(\App\Livewire\ShareDownload::class, ['share' => $share])
        ->assertSet('authenticated', true);
});

test('download counter increments on zip download', function () {
    Storage::fake('shares');

    $share = createShareWithFile();
    $share->load('files');

    $encryptionService = app(FileEncryptionService::class);
    $key = $share->encryption_key;

    foreach ($share->files as $file) {
        $dir = Storage::disk('shares')->path($share->token);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $encryptedPath = $dir.'/'.basename($file->stored_path);
        $tempSource = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempSource, 'test content');
        $encryptionService->encryptFile($tempSource, $encryptedPath, $key);
        unlink($tempSource);
    }

    $this->withSession(['share_password_'.$share->token => null])
        ->get(route('share.download.all', $share));

    expect($share->fresh()->download_count)->toBe(1);
});

test('share auto-deletes after reaching download limit', function () {
    Storage::fake('shares');

    $service = app(ShareService::class);
    $file = UploadedFile::fake()->create('file.txt', 100);

    $share = $service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'max_downloads' => 1,
    ]);

    $service->recordDownload($share);

    expect(Share::query()->find($share->id))->toBeNull();
});

/**
 * Helper to create a share with an actual encrypted file.
 */
function createShareWithFile(?string $password = null): Share
{
    $service = app(ShareService::class);
    $file = UploadedFile::fake()->create('testfile.txt', 100);

    return $service->createShare([
        ['file' => $file, 'relativePath' => null],
    ], [
        'password' => $password,
    ]);
}
