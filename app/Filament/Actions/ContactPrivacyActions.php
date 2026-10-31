<?php

namespace App\Filament\Actions;

use App\Enums\AuditEvent;
use App\Models\CrmContact;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ContactPrivacyService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exercice des droits RGPD depuis la fiche contact.
 *
 * Réservé aux administrateurs : un export ou une anonymisation engagent
 * l’entreprise et doivent rester traçables dans le journal d’audit.
 */
class ContactPrivacyActions
{
    public static function export(): Action
    {
        return Action::make('exportPersonalData')
            ->label('Exporter les données (RGPD)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => self::isAdmin())
            ->action(function (CrmContact $record): StreamedResponse {
                $payload = app(ContactPrivacyService::class)->export($record);

                app(AuditLogger::class)->record(
                    $record,
                    AuditEvent::Exported,
                    label: 'Export RGPD du contact #'.$record->getKey(),
                );

                $filename = 'contact-'.$record->getKey().'-'.now()->format('Y-m-d').'.json';

                return response()->streamDownload(
                    function () use ($payload): void {
                        echo json_encode(
                            $payload,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                        );
                    },
                    $filename,
                    ['Content-Type' => 'application/json'],
                );
            });
    }

    public static function anonymize(): Action
    {
        return Action::make('anonymize')
            ->label('Anonymiser (droit à l’effacement)')
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Anonymiser ce contact')
            ->modalDescription('Les nom, coordonnées et notes seront définitivement effacés. L’historique commercial (affaires, devis, activités, e-mails) est conservé sans donnée identifiante. Cette action est irréversible.')
            ->modalSubmitActionLabel('Anonymiser')
            ->visible(fn (): bool => self::isAdmin())
            ->action(function (CrmContact $record): void {
                app(ContactPrivacyService::class)->anonymize($record);

                Notification::make()
                    ->title('Contact anonymisé')
                    ->body('Les données identifiantes ont été effacées.')
                    ->success()
                    ->send();
            });
    }

    private static function isAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
