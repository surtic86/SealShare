<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class SystemPasswordPrompt extends Component
{
    #[Validate('required|string')]
    public string $password = '';

    public function verify(): void
    {
        $this->validate();

        $systemPassword = Setting::get('system_password');

        if (! $systemPassword || ! Hash::check($this->password, $systemPassword)) {
            $this->addError('password', __('The password is incorrect.'));

            return;
        }

        session(['system_password_verified' => true]);

        $this->redirect(route('upload'), navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.system-password-prompt');
    }
}
