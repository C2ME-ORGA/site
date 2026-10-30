<?php

use App\Enums\AccountStatus;
use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::factory()->create([
    'name' => 'Administrateur E2E',
    'email' => 'admin@e2e.test',
    'role' => UserRole::Admin,
    'is_active' => true,
]);

$cities = [
    ['Lille', '59000'],
    ['Lyon', '69003'],
    ['Nantes', '44000'],
    ['Bordeaux', '33000'],
    ['Toulouse', '31000'],
    ['Strasbourg', '67000'],
    ['Rennes', '35000'],
    ['Marseille', '13001'],
    ['Grenoble', '38000'],
    ['Dijon', '21000'],
    ['Rouen', '76000'],
    ['Tours', '37000'],
];

foreach ($cities as $index => [$city, $postalCode]) {
    $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

    $account = CrmAccount::factory()->create([
        'owner_id' => $user->id,
        'book' => CrmBook::BuildingCompanies,
        'category' => CrmCategory::Builders,
        'status' => AccountStatus::Prospect,
        'name' => 'E2E Compte '.$number,
        'legal_name' => 'E2E Compte '.$number,
        'address_line1' => ($index + 1).' rue du Test',
        'address_line2' => null,
        'postal_code' => $postalCode,
        'city' => $city,
        'country' => 'FR',
    ]);

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => null,
        'user_id' => $user->id,
        'type' => CrmActivityType::Visit,
        'subject' => 'Visite E2E '.$number,
        'body' => 'Compte rendu initial',
        'occurred_at' => null,
        'due_at' => now()->addDay(),
    ]);
}

echo 'E2E seed : '.CrmAccount::query()->count().' comptes, '
    .CrmActivity::query()->count().' activités.'.PHP_EOL;
