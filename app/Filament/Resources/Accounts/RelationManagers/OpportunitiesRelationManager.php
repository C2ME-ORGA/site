<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Enums\CrmSource;
use App\Enums\OpportunityStage;
use App\Models\CrmOpportunity;
use App\Services\CrmCsvExporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpportunitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'opportunities';

    protected static ?string $title = 'Affaires';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Intitulé')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('stage')
                    ->label('Étape')
                    ->options(OpportunityStage::options())
                    ->default(OpportunityStage::New->value)
                    ->required()
                    ->native(false),

                Select::make('source')
                    ->label('Source')
                    ->options(CrmSource::options())
                    ->native(false),

                TextInput::make('amount_estimated')
                    ->label('Montant estimé (€)')
                    ->numeric()
                    ->minValue(0),

                TextInput::make('probability')
                    ->label('Probabilité (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),

                DatePicker::make('expected_close_date')
                    ->label('Clôture prévue')
                    ->native(false),

                Select::make('owner_id')
                    ->label('Commercial')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                RichEditor::make('description')
                    ->label('Description')
                    ->disableToolbarButtons(['attachFiles'])
                    ->columnSpanFull(),

                Textarea::make('lost_reason')
                    ->label('Motif de perte')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultGroup('stage')
            ->groups(['stage'])
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('title')
                    ->label('Intitulé')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('stage')
                    ->label('Étape')
                    ->badge(),

                TextColumn::make('amount_estimated')
                    ->label('Montant estimé')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('probability')
                    ->label('Probabilité')
                    ->suffix('%')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('expected_close_date')
                    ->label('Clôture prévue')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label('Commercial')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),

                Action::make('exportCsv')
                    ->label('Exporter (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (): StreamedResponse {
                        $account = $this->getOwnerRecord();

                        /** @var Builder<CrmOpportunity> $query */
                        $query = $this->getTableQueryForExport();

                        return app(CrmCsvExporter::class)->opportunities(
                            'c2me-affaires-'.($account->getAttribute('code') ?: $account->getKey()).'-'.now()->format('Y-m-d').'.csv',
                            $query,
                        );
                    }),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('Imprimer')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (CrmOpportunity $record): string => route('crm.print.opportunity', $record))
                    ->openUrlInNewTab(),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false; // TODO: Change the autogenerated stub
    }
}
