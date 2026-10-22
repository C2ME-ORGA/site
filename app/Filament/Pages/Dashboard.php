<?php

namespace App\Filament\Pages;

use App\Support\DashboardPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Période')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->schema([
                        Select::make('period')
                            ->label('Affichage')
                            ->options([
                                'month' => 'Mois',
                                'quarter' => 'Trimestre',
                                'year' => 'Année',
                                'custom' => 'Période personnalisée',
                            ])
                            ->default('year')
                            ->live()
                            ->native(false),

                        Select::make('year')
                            ->label('Année')
                            ->options(self::yearOptions())
                            ->default((string) now()->year)
                            ->live()
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('period') !== 'custom'),

                        Select::make('month')
                            ->label('Mois')
                            ->options(DashboardPeriod::monthNames())
                            ->default((int) now()->month)
                            ->live()
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('period') === 'month'),

                        Select::make('quarter')
                            ->label('Trimestre')
                            ->options([
                                1 => 'T1',
                                2 => 'T2',
                                3 => 'T3',
                                4 => 'T4',
                            ])
                            ->default((int) ceil(now()->month / 3))
                            ->live()
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('period') === 'quarter'),

                        DatePicker::make('from')
                            ->label('Du')
                            ->live()
                            ->required(fn (Get $get): bool => $get('period') === 'custom')
                            ->visible(fn (Get $get): bool => $get('period') === 'custom'),

                        DatePicker::make('to')
                            ->label('Au')
                            ->live()
                            ->required(fn (Get $get): bool => $get('period') === 'custom')
                            ->visible(fn (Get $get): bool => $get('period') === 'custom'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function yearOptions(): array
    {
        $currentYear = (int) now()->year;
        $years = range($currentYear, $currentYear - 5);
        $options = [];

        foreach ($years as $year) {
            $options[(string) $year] = (string) $year;
        }

        return $options;
    }
}
