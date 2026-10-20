<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Emails\Pages\ListEmailMessages;
use App\Models\EmailMailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('la page Boîte de réception s\'affiche et contient l\'action Nouveau message', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@test.local',
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $mailbox = EmailMailbox::factory()->create([
        'email' => 'commercial@test.local',
        'is_shared' => true,
    ]);

    $response = Livewire::actingAs($admin)
        ->test(ListEmailMessages::class);

    $response->assertOk();

    // Vérifier que l'action « Nouveau message » est présente
    $response->assertSee('Nouveau message');

    // Vérifier que le bouton « Ouvrir les actions » est également présent
    $response->assertSee('Ouvrir les actions');
});
