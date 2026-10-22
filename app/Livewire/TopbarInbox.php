<?php

namespace App\Livewire;

use App\Filament\Resources\Emails\EmailMessageResource;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TopbarInbox extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.topbar-inbox', [
            'url' => EmailMessageResource::getUrl('index'),
            'unread' => EmailMessageResource::getNavigationBadge(),
            'channel' => $user instanceof User ? $user->receivesBroadcastNotificationsOn() : null,
        ]);
    }
}
