<?php

namespace App\Filament\Resources\Emails;

use App\Filament\Resources\Emails\Pages\ListEmailMessages;
use App\Filament\Resources\Emails\Pages\ViewEmailMessage;
use App\Filament\Resources\Emails\Schemas\EmailMessageInfolist;
use App\Filament\Resources\Emails\Tables\EmailMessagesTable;
use App\Models\EmailMessage;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EmailMessageResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = EmailMessage::class;

    protected static ?string $slug = 'emails';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $modelLabel = 'e-mail';

    protected static ?string $pluralModelLabel = 'boîte de réception';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = 'Boîte de réception';

    protected static ?int $navigationSort = 70;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    public static function shouldGenerateNavigationBadges(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('is_read', false)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmailMessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailMessagesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('mailbox', fn (Builder $query) => $query
            ->where('is_shared', true)
            ->orWhere('user_id', $user->id));
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEmailMessages::route('/'),
            'view' => ViewEmailMessage::route('/{record}'),
        ];
    }

    /**
     * Article d'aide contextuelle ouvert depuis le menu d'aide du panneau.
     *
     * @return array<int, string>
     */
    public static function getDocumentation(): array
    {
        return ['communication.emails'];
    }
}
