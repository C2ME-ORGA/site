<?php

namespace App\Filament\Widgets;

use App\Models\CrmOpportunity;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardFormat;
use App\Support\FilamentAccountUrl;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopOpenOpportunities extends TableWidget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top 5 des affaires ouvertes';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $query = $user instanceof User
            ? app(DashboardMetricsService::class)->topOpenOpportunitiesQuery($user)
            : CrmOpportunity::query()->whereRaw('1 = 0');

        return $table
            ->query($query)
            ->defaultSort('amount_estimated', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->toggleable(),

                TextColumn::make('title')
                    ->label('Affaire')
                    ->weight('bold')
                    ->limit(50),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->limit(35),

                TextColumn::make('stage')
                    ->label('Étape')
                    ->badge(),

                TextColumn::make('amount_estimated')
                    ->label('Montant')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => DashboardFormat::money($state)),

                TextColumn::make('probability')
                    ->label('Probabilité')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $state !== null
                        ? number_format((int) $state, 0, ',', ' ').' %'
                        : '—'),

                TextColumn::make('expected_close_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextColumn::make('owner.name')
                    ->label('Propriétaire')
                    ->placeholder('—')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->recordActions([
                Action::make('openAccount')
                    ->label('Ouvrir la fiche')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CrmOpportunity $record): string => FilamentAccountUrl::view($record->account)),
            ])
            ->emptyStateHeading('Aucune affaire ouverte')
            ->emptyStateDescription('Les affaires en cours apparaîtront ici.');
    }
}
