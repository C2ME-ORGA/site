<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Schema;

/**
 * Profil de l'utilisateur connecté.
 *
 * Filament fournit déjà le changement de mot de passe et d'adresse e-mail
 * (confirmation, contrôle du mot de passe actuel, limitation de débit). Depuis
 * que l'accès au panneau repose sur le rôle et non sur l'adresse, aucune garde
 * supplémentaire n'est nécessaire.
 *
 * La signature e-mail est propre à chaque utilisateur : elle est ajoutée
 * automatiquement au corps des nouveaux messages et des réponses. L'éditeur
 * enrichi permet de la mettre en forme et d'y insérer des images (logo, etc.).
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                RichEditor::make('email_signature')
                    ->label('Signature e-mail')
                    ->helperText('Ajoutée automatiquement aux nouveaux messages et aux réponses. Vous pouvez la mettre en forme et y insérer des images.')
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('email-signatures')
                    ->fileAttachmentsVisibility('public')
                    ->fileAttachmentsMaxSize(5120)
                    ->columnSpanFull(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
