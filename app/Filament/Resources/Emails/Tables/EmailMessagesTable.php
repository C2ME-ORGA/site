<?php

namespace App\Filament\Resources\Emails\Tables;

use App\Models\EmailMessage;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['account', 'contact']))
            ->columns([
                IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('gray')
                    ->falseColor('warning'),

                TextColumn::make('from_name')
                    ->label('Expéditeur')
                    ->state(fn (EmailMessage $record): string => $record->from_name ?? $record->from_email)
                    ->description(fn (EmailMessage $record): ?string => $record->from_name !== null ? $record->from_email : null)
                    ->searchable(['from_name', 'from_email'])
                    ->weight('bold'),

                TextColumn::make('subject')
                    ->label('Objet')
                    ->limit(60)
                    ->searchable()
                    ->placeholder('(sans objet)'),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('mailbox.name')
                    ->label('Boîte')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('received_at')
                    ->label('Reçu le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('email_mailbox_id')
                    ->label('Boîte')
                    ->relationship('mailbox', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_read')
                    ->label('Lecture')
                    ->placeholder('Tous')
                    ->trueLabel('Lus')
                    ->falseLabel('Non lus'),

                SelectFilter::make('crm_account_id')
                    ->label('Compte')
                    ->relationship('account', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('toggleRead')
                    ->label(fn (EmailMessage $record): string => $record->is_read ? 'Marquer non lu' : 'Marquer lu')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->action(fn (EmailMessage $record) => $record->forceFill(['is_read' => ! $record->is_read])->save()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('markRead')
                        ->label('Marquer comme lu')
                        ->icon('heroicon-o-envelope-open')
                        ->color('success')
                        ->action(function (array $records): void {
                            foreach ($records as $record) {
                                $record->forceFill(['is_read' => true])->save();
                            }

                            Notification::make()
                                ->title(count($records).' message(s) marqué(s) comme lu')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
