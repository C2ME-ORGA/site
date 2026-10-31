<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardFormat;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class QuotesToFollowUp extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Devis à relancer ou expirés';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $query = $user instanceof User
            ? app(DashboardMetricsService::class)->quotesToFollowUpQuery(
                $user,
                (int) config('crm.dashboard.quotes_to_follow_up_days', 7),
            )
            : Quote::query()->whereRaw('1 = 0');

        return $table
            ->query($query)
            ->defaultSort('valid_until')
            ->paginated(false)
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->toggleable(),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->limit(35),

                TextColumn::make('amount_ht')
                    ->label('Montant HT')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => DashboardFormat::money($state)),

                TextColumn::make('valid_until')
                    ->label('Validité')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (Quote $record): string => match (true) {
                        $record->valid_until?->isPast() ?? false => 'danger',
                        $record->valid_until?->lessThanOrEqualTo(now()->addDays(7)) ?? false => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('owner.name')
                    ->label('Propriétaire')
                    ->placeholder('—')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Ouvrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Quote $record): string => QuoteResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Quote $record): bool => auth()->user()?->can('update', $record) ?? false),
            ])
            ->emptyStateHeading('Aucun devis à relancer')
            ->emptyStateDescription('Les devis proches de leur échéance ou expirés apparaîtront ici.');
    }
}
