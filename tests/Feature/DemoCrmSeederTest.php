<?php

use App\Enums\CrmBook;
use App\Enums\QuoteDocumentStatus;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\DemoCrmSeeder;

test('le seeder de démonstration remplit les trois carnets', function (): void {
    $this->seed(DemoCrmSeeder::class);

    expect(CrmAccount::query()->count())->toBe(12)
        ->and(CrmAccount::query()->where('book', CrmBook::Prescribers)->count())->toBe(4)
        ->and(CrmAccount::query()->where('book', CrmBook::IndustrialClients)->count())->toBe(4)
        ->and(CrmAccount::query()->where('book', CrmBook::BuildingCompanies)->count())->toBe(4)
        ->and(CrmContact::query()->count())->toBe(24)
        ->and(Quote::query()->count())->toBe(12)
        ->and(Quote::query()->where('status', QuoteDocumentStatus::Sent)->count())->toBe(12)
        ->and(EmailMessage::query()->count())->toBe(4)
        ->and(User::query()->where('email', 'demo.commercial@c2me.fr')->exists())->toBeTrue();
});
