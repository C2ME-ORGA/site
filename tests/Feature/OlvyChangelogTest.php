<?php

use App\Enums\UserRole;
use App\Models\User;

test('le changelog Olvy est chargé pour un utilisateur connecté', function (): void {
    $admin = User::factory()->create([
        'email' => config('site.admin.allowed_emails')[0],
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('var OlvyConfig = { workspaceAlias: "c2meapp" };', false)
        ->assertSee('https://app.olvy.co/scriptV3.js', false)
        ->assertSee('id="olvy-whats-new"', false);
});

test('le changelog Olvy est absent de l’écran de connexion', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('app.olvy.co/scriptV3.js', false);
});
