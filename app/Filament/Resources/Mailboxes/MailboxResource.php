<?php

namespace App\Filament\Resources\Mailboxes;

use App\Filament\Resources\Mailboxes\Pages\CreateMailbox;
use App\Filament\Resources\Mailboxes\Pages\EditMailbox;
use App\Filament\Resources\Mailboxes\Pages\ListMailboxes;
use App\Filament\Resources\Mailboxes\Schemas\MailboxForm;
use App\Filament\Resources\Mailboxes\Tables\MailboxesTable;
use App\Models\EmailMailbox;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MailboxResource extends Resource
{
    protected static ?string $model = EmailMailbox::class;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?string $modelLabel = 'boîte e-mail';

    protected static ?string $pluralModelLabel = 'boîtes e-mail';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Boîtes e-mail';

    protected static ?int $navigationSort = 80;

    protected static string|UnitEnum|null $navigationGroup = 'Système';

    public static function form(Schema $schema): Schema
    {
        return MailboxForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailboxesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('is_shared', true)
            ->orWhere('user_id', $user->id));
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListMailboxes::route('/'),
            'create' => CreateMailbox::route('/create'),
            'edit' => EditMailbox::route('/{record}/edit'),
        ];
    }
}
