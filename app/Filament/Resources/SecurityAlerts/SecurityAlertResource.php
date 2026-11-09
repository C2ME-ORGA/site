<?php

namespace App\Filament\Resources\SecurityAlerts;

use App\Filament\Resources\SecurityAlerts\Pages\ListSecurityAlerts;
use App\Filament\Resources\SecurityAlerts\Pages\ViewSecurityAlert;
use App\Services\SecurityAlertWorkflow;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;
use MKWebDesign\FilamentWatchdog\Resources\SecurityAlertResource as BaseSecurityAlertResource;

/**
 * Alertes de sécurité en lecture seule, avec deux transitions métier
 * contrôlées : acquitter puis résoudre. Aucun champ technique n'est
 * modifiable depuis l'interface.
 */
class SecurityAlertResource extends BaseSecurityAlertResource
{
    protected static ?string $navigationLabel = 'Alertes de sécurité';

    protected static string|\UnitEnum|null $navigationGroup = 'Système';

    protected static ?int $navigationSort = 98;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('alert_type')
                    ->label('Type')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('severity')
                    ->label('Gravité')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'info',
                        'high' => 'warning',
                        'critical' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'acknowledged' => 'info',
                        'resolved' => 'success',
                        'false_positive' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Reçue le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('severity')
                    ->label('Gravité')
                    ->options([
                        'low' => 'Faible',
                        'medium' => 'Moyenne',
                        'high' => 'Élevée',
                        'critical' => 'Critique',
                    ]),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'new' => 'Nouvelle',
                        'acknowledged' => 'Acquittée',
                        'resolved' => 'Résolue',
                        'false_positive' => 'Faux positif',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                static::acknowledgeAction(),
                static::resolveAction(),
            ]);
    }

    public static function acknowledgeAction(): Action
    {
        return Action::make('acknowledge')
            ->label('Acquitter')
            ->icon('heroicon-o-check-circle')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Acquitter cette alerte ?')
            ->modalDescription('L’alerte est prise en compte ; elle reste visible jusqu’à sa résolution.')
            ->visible(fn (SecurityAlert $record): bool => Gate::allows('acknowledge', $record)
                && SecurityAlertWorkflow::canAcknowledge($record))
            ->action(function (SecurityAlert $record): void {
                Gate::authorize('acknowledge', $record);

                try {
                    app(SecurityAlertWorkflow::class)->acknowledge($record, auth()->user());
                } catch (DomainException) {
                    Notification::make()
                        ->title('Transition impossible')
                        ->body('Cette alerte a déjà été traitée.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Alerte acquittée')
                    ->success()
                    ->send();
            });
    }

    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label('Résoudre')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Résoudre cette alerte ?')
            ->modalDescription('À utiliser après vérification : l’alerte est classée comme traitée.')
            ->visible(fn (SecurityAlert $record): bool => Gate::allows('resolve', $record)
                && SecurityAlertWorkflow::canResolve($record))
            ->action(function (SecurityAlert $record): void {
                Gate::authorize('resolve', $record);

                try {
                    app(SecurityAlertWorkflow::class)->resolve($record, auth()->user());
                } catch (DomainException) {
                    Notification::make()
                        ->title('Transition impossible')
                        ->body('Cette alerte a déjà été résolue.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Alerte résolue')
                    ->success()
                    ->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alerte')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('alert_type')->label('Type'),
                        TextEntry::make('severity')
                            ->label('Gravité')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'low' => 'success',
                                'medium' => 'info',
                                'high' => 'warning',
                                'critical' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('title')->label('Titre')->columnSpanFull(),
                        TextEntry::make('description')->label('Description')->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'new' => 'warning',
                                'acknowledged' => 'info',
                                'resolved' => 'success',
                                'false_positive' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')->label('Reçue le')->dateTime('d/m/Y H:i'),
                        TextEntry::make('acknowledged_at')->label('Acquittée le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('acknowledged_by')->label('Acquittée par')->placeholder('—'),
                        TextEntry::make('resolved_at')->label('Résolue le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('resolved_by')->label('Résolue par')->placeholder('—'),
                        TextEntry::make('metadata')
                            ->label('Détails techniques')
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state): string => is_array($state)
                                ? (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                : (string) $state)
                            ->extraAttributes(['class' => 'whitespace-pre-wrap font-mono text-xs']),
                    ]),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSecurityAlerts::route('/'),
            'view' => ViewSecurityAlert::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
