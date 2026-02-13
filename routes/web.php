<?php

use App\Http\Controllers\DownloadController;
use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Admin\AdminSettings;
use App\Livewire\FileUploader;
use App\Livewire\SetupWizard;
use App\Livewire\ShareCreated;
use App\Livewire\ShareDownload;
use App\Livewire\SystemPasswordPrompt;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('upload');
})->name('home');

Route::livewire('setup', SetupWizard::class)->name('setup');

Route::livewire('system-password', SystemPasswordPrompt::class)->name('system-password');

Route::middleware(['system.password'])->group(function () {
    Route::livewire('upload', FileUploader::class)->name('upload');
    Route::livewire('share/{share:token}/created', ShareCreated::class)->name('share.created');
});

Route::livewire('s/{share:token}', ShareDownload::class)->name('share.download');
Route::get('s/{share:token}/download', [DownloadController::class, 'download'])->name('share.download.all');
Route::get('s/{share:token}/download/{shareFile}', [DownloadController::class, 'downloadFile'])->name('share.download.file');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('dashboard', AdminDashboard::class)->name('admin.dashboard');
    Route::livewire('settings', AdminSettings::class)->name('admin.settings');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
