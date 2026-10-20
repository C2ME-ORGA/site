<?php

namespace App\Livewire;

use App\Filament\Resources\Emails\EmailMessageResource;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TopbarInbox extends Component
{
    public function render(): View
    {
        return view('livewire.topbar-inbox', [
            'url' => EmailMessageResource::getUrl('index'),
            'unread' => EmailMessageResource::getNavigationBadge(),
        ]);
    }
}
