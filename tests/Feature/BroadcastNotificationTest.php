<?php

use App\Models\EmailMessage;
use App\Models\User;
use App\Notifications\InboundEmailReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

test('le canal privé des notifications est limité à son propriétaire', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    $user = User::factory()->create();
    $other = User::factory()->create();

    // Les canaux sont enregistrés au démarrage avec le pilote de test « null ».
    // On recharge le fichier après avoir choisi Reverb pour tester les autorisations.
    require base_path('routes/channels.php');

    $requestFor = function (int $id) use ($user): Request {
        $request = Request::create('/broadcasting/auth', 'POST', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$id,
        ]);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    };

    expect(fn () => Broadcast::auth($requestFor($other->id)))
        ->toThrow(AccessDeniedHttpException::class);

    expect(Broadcast::auth($requestFor($user->id)))
        ->toBeArray()
        ->toHaveKey('auth');
});

test('la notification e-mail produit un message Filament diffusable', function (): void {
    $message = EmailMessage::factory()->create([
        'from_name' => 'Jean Dupont',
        'subject' => '=?UTF-8?Q?Demande_de_devis?=',
        'is_read' => false,
        'is_sent' => false,
    ]);

    $notification = new InboundEmailReceived($message);
    $payload = $notification->toBroadcast(User::factory()->make())->data;

    expect($payload['format'])->toBe('filament')
        ->and($payload['title'])->toContain('Nouvel e-mail de Jean Dupont')
        ->and($payload['body'])->toBe('Demande de devis')
        ->and($payload['actions'][0]['url'])->toContain('/admin/emails/'.$message->id);
});
