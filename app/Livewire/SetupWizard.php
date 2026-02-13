<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class SetupWizard extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|email|max:255|unique:users')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (User::query()->where('is_admin', true)->exists()) {
            $this->redirect(route('upload'), navigate: true);
        }
    }

    public function createAdmin(): void
    {
        if (User::query()->where('is_admin', true)->exists()) {
            $this->redirect(route('upload'), navigate: true);

            return;
        }

        $this->validate();

        $user = User::query()->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'email_verified_at' => now(),
        ]);

        $user->is_admin = true;
        $user->save();

        Setting::set('setup_complete', 'true');

        Auth::login($user);

        $this->redirect(route('admin.dashboard'), navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.setup-wizard');
    }
}
