<?php

namespace App\Filament\Resources\Emails\Schemas;

use App\Models\EmailMessage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailMessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('from_email')
                            ->label('De')
                            ->state(fn (EmailMessage $record): string => $record->from_name !== null
                                ? $record->from_name.' <'.$record->from_email.'>'
                                : $record->from_email),

                        TextEntry::make('received_at')
                            ->label('Reçu le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('to_email')
                            ->label('À')
                            ->state(fn (EmailMessage $record): string => self::addresses($record->to_recipients)),

                        TextEntry::make('cc_email')
                            ->label('Copie')
                            ->state(fn (EmailMessage $record): string => self::addresses($record->cc_recipients))
                            ->visible(fn (EmailMessage $record): bool => $record->cc_recipients !== null && $record->cc_recipients !== []),

                        TextEntry::make('subject')
                            ->label('Objet')
                            ->placeholder('(sans objet)')
                            ->columnSpanFull(),

                        TextEntry::make('mailbox.name')
                            ->label('Boîte')
                            ->placeholder('—'),

                        TextEntry::make('received_at_mailbox')
                            ->label('Type')
                            ->badge()
                            ->state(fn (EmailMessage $record): string => $record->is_sent ? 'Sortant' : 'Entrant')
                            ->color(fn (EmailMessage $record): string => $record->is_sent ? 'info' : 'gray'),

                        TextEntry::make('account_id')
                            ->label('Compte')
                            ->state(fn (EmailMessage $record): ?string => $record->account?->name)
                            ->placeholder('—'),

                        TextEntry::make('contact_id')
                            ->label('Contact')
                            ->state(fn (EmailMessage $record): ?string => $record->contact?->fullName())
                            ->placeholder('—'),
                    ]),

                Section::make('Contenu')
                    ->schema([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->state(fn (EmailMessage $record): string => self::body($record))
                            ->placeholder('(message vide)')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<int, array{email?: string, name?: string|null}>|null  $recipients
     */
    private static function addresses(?array $recipients): string
    {
        if ($recipients === null || $recipients === []) {
            return '—';
        }

        return implode(', ', array_map(
            fn (array $recipient): string => $recipient['name'] ?? $recipient['email'] ?? '',
            $recipients,
        ));
    }

    private static function body(EmailMessage $record): string
    {
        if ($record->body_text !== null && $record->body_text !== '') {
            return $record->body_text;
        }

        return trim(strip_tags((string) $record->body_html));
    }
}
