<?php

namespace App\Support;

use App\Enums\AccountStatus;
use App\Models\CrmAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class FollowUpAccounts
{
    /**
     * Comptes dont la relance est due ou dépassée.
     *
     * @return Builder<CrmAccount>
     */
    public static function query(?User $user = null, bool $includeInactive = false): Builder
    {
        return CrmAccount::query()
            ->followUpDue()
            ->when(
                ! $includeInactive,
                fn (Builder $query): Builder => $query->where('status', '!=', AccountStatus::Inactive->value),
            )
            ->when(
                $user !== null,
                fn (Builder $query): Builder => $query->ownedBy($user),
            );
    }

    /**
     * Comptes dont la relance est due ou dépassée, hors comptes inactifs.
     *
     * @return Builder<CrmAccount>
     */
    public static function due(?User $user = null): Builder
    {
        return self::query($user);
    }
}
