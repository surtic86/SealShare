<?php

use App\Models\User;
use Livewire\Livewire;

test('setup wizard renders when no admin exists', function () {
    User::query()->where('is_admin', true)->delete();

    $response = $this->get(route('setup'));

    $response->assertOk();
});

test('setup wizard redirects to upload when admin already exists', function () {
    Livewire::test(\App\Livewire\SetupWizard::class)
        ->assertRedirect(route('upload'));
});

test('setup wizard creates admin user', function () {
    User::query()->where('is_admin', true)->delete();

    Livewire::test(\App\Livewire\SetupWizard::class)
        ->set('name', 'Admin User')
        ->set('email', 'admin@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('createAdmin')
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.com',
        'is_admin' => true,
    ]);

    $admin = User::query()->where('email', 'admin@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->is_admin)->toBeTrue();
});

test('setup wizard validates required fields', function () {
    User::query()->where('is_admin', true)->delete();

    Livewire::test(\App\Livewire\SetupWizard::class)
        ->set('name', '')
        ->set('email', '')
        ->set('password', '')
        ->call('createAdmin')
        ->assertHasErrors(['name', 'email', 'password']);
});

test('all routes redirect to setup when no admin exists', function () {
    User::query()->where('is_admin', true)->delete();

    $this->get(route('home'))->assertRedirect(route('setup'));
    $this->get(route('login'))->assertRedirect(route('setup'));
});
