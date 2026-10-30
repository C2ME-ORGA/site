<?php

use App\Enums\CrmActivityType;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmActivityAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function attachmentUser(UserRole $role): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => true,
    ]);
}

function attachmentActivity(User $owner): CrmActivity
{
    $account = CrmAccount::factory()->create(['owner_id' => $owner->id]);

    return CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => null,
        'user_id' => $owner->id,
        'type' => CrmActivityType::Visit,
        'subject' => 'Visite avec photo',
    ]);
}

beforeEach(function (): void {
    Storage::fake('local');
});

test('une photo peut être ajoutée à une activité', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);
    $clientUuid = (string) Str::uuid();

    $response = $this->actingAs($commercial)
        ->post(
            '/terrain/activities/'.$activity->id.'/attachments',
            [
                'photo' => UploadedFile::fake()->image('chantier.jpg', 800, 600),
                'client_uuid' => $clientUuid,
            ],
            ['Accept' => 'application/json'],
        );

    $response
        ->assertCreated()
        ->assertJsonPath('attachment.original_name', 'chantier.jpg')
        ->assertJsonPath('attachment.client_uuid', $clientUuid)
        ->assertJsonPath('attachment.mime_type', 'image/jpeg');

    $attachment = CrmActivityAttachment::query()->sole();

    expect($attachment->crm_activity_id)->toBe($activity->id)
        ->and($response->json('attachment.id'))->toBe($attachment->id);

    Storage::disk('local')->assertExists($attachment->path);
});

test('l’ajout d’une photo est idempotent avec client_uuid', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);
    $clientUuid = (string) Str::uuid();
    $payload = [
        'photo' => UploadedFile::fake()->image('photo.jpg', 400, 400),
        'client_uuid' => $clientUuid,
    ];

    $first = $this->actingAs($commercial)
        ->post('/terrain/activities/'.$activity->id.'/attachments', $payload, ['Accept' => 'application/json'])
        ->assertCreated();

    $this->actingAs($commercial)
        ->post(
            '/terrain/activities/'.$activity->id.'/attachments',
            [
                'photo' => UploadedFile::fake()->image('photo.jpg', 400, 400),
                'client_uuid' => $clientUuid,
            ],
            ['Accept' => 'application/json'],
        )
        ->assertOk()
        ->assertJsonPath('attachment.id', $first->json('attachment.id'));

    expect(CrmActivityAttachment::query()->count())->toBe(1);
});

test('les photos invalides ou trop lourdes sont refusées', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);

    $this->actingAs($commercial)
        ->post(
            '/terrain/activities/'.$activity->id.'/attachments',
            ['photo' => UploadedFile::fake()->create('note.txt', 10, 'text/plain')],
            ['Accept' => 'application/json'],
        )
        ->assertUnprocessable();

    $this->actingAs($commercial)
        ->post(
            '/terrain/activities/'.$activity->id.'/attachments',
            ['photo' => UploadedFile::fake()->create('enorme.jpg', 6000, 'image/jpeg')],
            ['Accept' => 'application/json'],
        )
        ->assertUnprocessable();

    expect(CrmActivityAttachment::query()->count())->toBe(0);
});

test('le nombre maximum de photos par activité est appliqué', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);

    foreach (range(1, 5) as $index) {
        CrmActivityAttachment::query()->create([
            'crm_activity_id' => $activity->id,
            'path' => 'photos/'.$index.'.jpg',
            'original_name' => $index.'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);
    }

    $this->actingAs($commercial)
        ->post(
            '/terrain/activities/'.$activity->id.'/attachments',
            ['photo' => UploadedFile::fake()->image('sixieme.jpg', 400, 400)],
            ['Accept' => 'application/json'],
        )
        ->assertUnprocessable();

    expect(CrmActivityAttachment::query()->count())->toBe(5);
});

test('un commercial ne peut pas ajouter de photo à l’activité d’un autre commercial', function (): void {
    $owner = attachmentUser(UserRole::Admin);
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($owner);

    $this->actingAs($commercial)
        ->post(
            '/terrain/activities/'.$activity->id.'/attachments',
            ['photo' => UploadedFile::fake()->image('interdit.jpg', 400, 400)],
            ['Accept' => 'application/json'],
        )
        ->assertForbidden();

    expect(CrmActivityAttachment::query()->count())->toBe(0);
});

test('une photo est consultable via une route authentifiée et protégée', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $other = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);

    $attachmentId = $this->actingAs($commercial)
        ->post(
            '/terrain/activities/'.$activity->id.'/attachments',
            ['photo' => UploadedFile::fake()->image('plan.jpg', 400, 400)],
            ['Accept' => 'application/json'],
        )
        ->assertCreated()
        ->json('attachment.id');

    $this->actingAs($commercial)
        ->get('/terrain/attachments/'.$attachmentId)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    $this->actingAs($other)
        ->get('/terrain/attachments/'.$attachmentId)
        ->assertForbidden();
});

test('supprimer une activité supprime ses photos du disque', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);

    Storage::disk('local')->put('photos/activite.jpg', 'contenu');

    $attachment = CrmActivityAttachment::query()->create([
        'crm_activity_id' => $activity->id,
        'path' => 'photos/activite.jpg',
        'original_name' => 'activite.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $activity->delete();

    expect(CrmActivityAttachment::query()->find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing('photos/activite.jpg');
});

test('la purge des activités supprime aussi les photos', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);
    $activity->update(['occurred_at' => now()->subMonths(61)]);

    Storage::disk('local')->put('photos/ancienne.jpg', 'contenu');

    CrmActivityAttachment::query()->create([
        'crm_activity_id' => $activity->id,
        'path' => 'photos/ancienne.jpg',
        'original_name' => 'ancienne.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $this->artisan('crm:purge-activities')->assertSuccessful();

    expect(CrmActivity::query()->find($activity->id))->toBeNull()
        ->and(CrmActivityAttachment::query()->count())->toBe(0);

    Storage::disk('local')->assertMissing('photos/ancienne.jpg');
});

test('les photos sont exposées dans le payload des activités', function (): void {
    $commercial = attachmentUser(UserRole::Commercial);
    $activity = attachmentActivity($commercial);

    CrmActivityAttachment::query()->create([
        'crm_activity_id' => $activity->id,
        'path' => 'photos/payload.jpg',
        'original_name' => 'payload.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 2048,
    ]);

    $this->actingAs($commercial)
        ->getJson('/terrain/activities')
        ->assertOk()
        ->assertJsonPath('activities.0.attachments.0.original_name', 'payload.jpg')
        ->assertJsonPath('activities.0.attachments.0.size', 2048)
        ->assertJsonPath('activities.0.attachments.0.url', route('field.attachments.show', CrmActivityAttachment::query()->sole()));
});

test('le téléchargement d’une photo exige une connexion', function (): void {
    $this->get('/terrain/attachments/1')->assertRedirect(route('filament.admin.auth.login'));
});

test('le back-office affiche les photos de l’activité', function (): void {
    $admin = attachmentUser(UserRole::Admin);
    $activity = attachmentActivity($admin);

    Storage::disk('local')->put('photos/admin.jpg', 'contenu');

    $attachment = CrmActivityAttachment::query()->create([
        'crm_activity_id' => $activity->id,
        'path' => 'photos/admin.jpg',
        'original_name' => 'admin.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $this->actingAs($admin)
        ->get('/admin/activities/'.$activity->id.'/edit')
        ->assertOk()
        ->assertSee('Photos')
        ->assertSee(route('field.attachments.show', $attachment), false);
});
