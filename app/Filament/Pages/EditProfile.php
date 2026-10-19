<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;

/**
 * Profil de l'utilisateur connecté.
 *
 * Filament fournit déjà le changement de mot de passe et d'adresse e-mail
 * (confirmation, contrôle du mot de passe actuel, limitation de débit). Depuis
 * que l'accès au panneau repose sur le rôle et non sur l'adresse, aucune garde
 * supplémentaire n'est nécessaire.
 */
class EditProfile extends BaseEditProfile {}
