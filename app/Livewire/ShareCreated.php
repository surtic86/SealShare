<?php

namespace App\Livewire;

use App\Models\Share;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ShareCreated extends Component
{
    public Share $share;

    public function mount(Share $share): void
    {
        $this->share = $share;
    }

    public function render(): mixed
    {
        return view('livewire.share-created');
    }
}
