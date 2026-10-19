<?php

namespace App\Filament\Resources\Mailboxes\Tables;

use App\Models\EmailMailbox;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MailboxesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Libellé')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Adresse')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->placeholder('Partagée'),

                IconColumn::make('is_shared')
                    ->label('Partagée')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('last_synced_at')
                    ->label('Dernière synchro')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Jamais')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_shared')
                    ->label('Partagée'),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Tester la connexion')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (EmailMailbox $record): void {
                        $result = self::testConnection($record);

                        Notification::make()
                            ->title($result['ok'] ? 'Connexion réussie' : 'Connexion impossible')
                            ->body($result['message'])
                            ->{$result['ok'] ? 'success' : 'danger'}()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Vérifie que le serveur IMAP répond sur son port.
     *
     * @return array{ok: bool, message: string}
     */
    private static function testConnection(EmailMailbox $mailbox): array
    {
        $transport = match ($mailbox->imap_encryption) {
            'ssl' => 'ssl://',
            default => '',
        };

        $target = $transport.$mailbox->imap_host.':'.$mailbox->imap_port;

        $connection = @fsockopen(
            $transport.$mailbox->imap_host,
            $mailbox->imap_port,
            $errorCode,
            $errorMessage,
            5,
        );

        if ($connection === false) {
            return [
                'ok' => false,
                'message' => sprintf('Impossible de joindre %s (%s).', $target, $errorMessage ?: 'erreur '.$errorCode),
            ];
        }

        fclose($connection);

        return [
            'ok' => true,
            'message' => sprintf('Le serveur %s répond correctement.', $target),
        ];
    }
}
