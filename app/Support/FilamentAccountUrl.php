<?php

namespace App\Support;

use App\Enums\CrmBook;
use App\Models\CrmAccount;

final class FilamentAccountUrl
{
    /**
     * URL de la fiche compte, selon le carnet auquel il appartient.
     */
    public static function view(CrmAccount $account): string
    {
        return route('filament.admin.resources.'.self::resource($account).'.view', ['record' => $account]);
    }

    /**
     * URL de modification du compte, selon le carnet auquel il appartient.
     */
    public static function edit(CrmAccount $account): string
    {
        return route('filament.admin.resources.'.self::resource($account).'.edit', ['record' => $account]);
    }

    private static function resource(CrmAccount $account): string
    {
        return match ($account->book) {
            CrmBook::Prescribers => 'prescriber-accounts',
            CrmBook::IndustrialClients => 'industrial-accounts',
            CrmBook::BuildingCompanies => 'building-company-accounts',
        };
    }
}
