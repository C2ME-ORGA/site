<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\CreateActivity;
use App\Filament\Resources\Activities\Pages\EditActivity;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Schemas\ActivityForm;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\CrmActivity;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = CrmActivity::class;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $modelLabel = 'activité';

    protected static ?string $pluralModelLabel = 'activités';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Activités & relances';

    protected static ?int $navigationSort = 16;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    public static function form(Schema $schema): Schema
    {
        return ActivityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $count = CrmActivity::query()->forUser($user)->dueToday()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }
}
