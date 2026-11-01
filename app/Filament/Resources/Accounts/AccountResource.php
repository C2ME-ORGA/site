<?php

namespace App\Filament\Resources\Accounts;

use App\Enums\CrmBook;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\Accounts\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Accounts\RelationManagers\OpportunitiesRelationManager;
use App\Filament\Resources\Accounts\RelationManagers\QuotesRelationManager;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Resources\Accounts\Schemas\AccountInfolist;
use App\Filament\Resources\Accounts\Tables\AccountsTable;
use App\Models\CrmAccount;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;

abstract class AccountResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = CrmAccount::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'compte';

    protected static ?string $pluralModelLabel = 'comptes';

    abstract public static function book(): CrmBook;

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema, static::book());
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountInfolist::configure($schema, static::book());
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            OpportunitiesRelationManager::class,
            QuotesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('book', static::book()->value);
        $user = auth()->user();

        if ($user !== null && ! $user->isAdmin()) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_active;
    }

    /**
     * Article d'aide contextuelle ouvert depuis le menu d'aide du panneau.
     *
     * @return array<int, string>
     */
    public static function getDocumentation(): array
    {
        return ['crm.comptes'];
    }
}
