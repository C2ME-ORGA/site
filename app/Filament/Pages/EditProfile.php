<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\Rule;

/**
 * Profil de l'administrateur.
 *
 * Filament fournit déjà le changement de mot de passe : confirmation, contrôle
 * du mot de passe actuel, limitation de débit et mise à jour du hash de
 * session. Cette page ne surcharge donc rien de ce qu'il fait de mieux.
 *
 * Elle n'ajoute qu'une garde. L'accès au panneau est conditionné par
 * l'adresse e-mail (voir User::canAccessPanel) : sans contrôle, l'administrateur
 * pourrait enregistrer une adresse hors liste et se verrouiller lui-même, la
 * seule récupération passant alors par la configuration serveur.
 */
class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->rule(Rule::in($this->allowedEmails()))
            ->validationMessages([
                'in' => 'Cette adresse n’est pas autorisée à accéder au back-office. '
                    .'Ajoutez-la d’abord dans admin.allowed_emails.',
            ]);
    }

    /**
     * Liste des adresses disposant de l'accès au panneau.
     *
     * @return array<int, string>
     */
    private function allowedEmails(): array
    {
        return config('site.admin.allowed_emails', []);
    }
}
