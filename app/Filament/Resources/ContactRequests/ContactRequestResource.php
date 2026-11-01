<?php

namespace App\Filament\Resources\ContactRequests;

use App\Filament\Resources\ContactRequests\Pages\CreateContactRequest;
use App\Filament\Resources\ContactRequests\Pages\EditContactRequest;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Filament\Resources\ContactRequests\Schemas\ContactRequestForm;
use App\Filament\Resources\ContactRequests\Schemas\ContactRequestInfolist;
use App\Filament\Resources\ContactRequests\Tables\ContactRequestsTable;
use App\Models\ContactRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use UnitEnum;

class ContactRequestResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = ContactRequest::class;

    protected static string|UnitEnum|null $navigationGroup = 'Demandes';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'demande de contact';

    protected static ?string $pluralModelLabel = 'demandes de contact';

    public static function shouldGenerateNavigationBadges(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactRequest::unread()->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return ContactRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContactRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
            'create' => CreateContactRequest::route('/create'),
            'view' => ViewContactRequest::route('/{record}'),
            'edit' => EditContactRequest::route('/{record}/edit'),
        ];
    }

    /**
     * Article d'aide contextuelle ouvert depuis le menu d'aide du panneau.
     *
     * @return array<int, string>
     */
    public static function getDocumentation(): array
    {
        return ['demandes.contacts'];
    }
}
