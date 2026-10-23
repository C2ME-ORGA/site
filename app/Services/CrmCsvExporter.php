<?php

namespace App\Services;

use App\Enums\CrmBook;
use App\Enums\CrmSource;
use App\Enums\CrmTagColor;
use App\Models\CrmAccount;
use App\Models\CrmOpportunity;
use App\Models\CrmTag;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CrmCsvExporter
{
    /**
     * @param  Builder<CrmAccount>  $query
     */
    public function accounts(string $filename, Builder $query, CrmBook $book): StreamedResponse
    {
        $rows = (function () use ($query, $book): \Generator {
            foreach ($query->with(['owner', 'tags'])->cursor() as $account) {
                yield $this->accountRow($account, $book);
            }
        })();

        return $this->download($filename, $this->accountHeaders($book), $rows);
    }

    /**
     * @param  Builder<CrmOpportunity>  $query
     */
    public function opportunities(string $filename, Builder $query): StreamedResponse
    {
        $rows = (function () use ($query): \Generator {
            foreach ($query->with(['account', 'contact', 'owner'])->cursor() as $opportunity) {
                yield $this->opportunityRow($opportunity);
            }
        })();

        return $this->download($filename, $this->opportunityHeaders(), $rows);
    }

    /**
     * @return list<string>
     */
    public function accountHeaders(CrmBook $book): array
    {
        $headers = [
            'Code',
            'Société',
            'Raison sociale',
            'Carnet',
            'Catégorie',
            'Statut',
            'Potentiel',
            'Source',
            'Commercial',
            'SIRET',
            'N° TVA',
            'Site web',
            'E-mail',
            'Téléphone',
            'Mobile',
            'Adresse',
            'Complément',
            'Code postal',
            'Ville',
            'Pays',
            'Dernier contact',
            'Prochaine relance',
            'Étiquettes',
            'Couleurs des étiquettes',
            'Notes',
        ];

        foreach ($this->metadataFields($book) as $field) {
            $headers[] = (string) ($field['label'] ?? $field['key'] ?? '');
        }

        return $headers;
    }

    /**
     * @return list<string>
     */
    public function opportunityHeaders(): array
    {
        return [
            'Référence',
            'Intitulé',
            'Compte',
            'Contact',
            'Étape',
            'Montant estimé',
            'Probabilité (%)',
            'Clôture prévue',
            'Source',
            'Commercial',
            'Gagnée le',
            'Clôturée le',
            'Motif de perte',
            'Description',
            'Créée le',
        ];
    }

    /**
     * @return list<string>
     */
    private function accountRow(CrmAccount $account, CrmBook $book): array
    {
        $row = [
            $this->text($account->code),
            $account->name,
            $this->text($account->legal_name),
            $account->book->getLabel(),
            $account->category->getLabel(),
            $account->status->getLabel(),
            $account->potential?->getLabel() ?? '',
            $account->source?->getLabel() ?? '',
            $this->text($account->owner?->name),
            $this->text($account->siret),
            $this->text($account->vat_number),
            $this->text($account->website),
            $this->text($account->email),
            $this->text($account->phone),
            $this->text($account->mobile),
            $this->text($account->address_line1),
            $this->text($account->address_line2),
            $this->text($account->postal_code),
            $this->text($account->city),
            $this->text($account->country),
            $account->last_contacted_at?->format('d/m/Y') ?? '',
            $account->next_follow_up_at?->format('d/m/Y') ?? '',
            $account->tags->pluck('name')->implode(', '),
            $account->tags
                ->map(fn (CrmTag $tag): string => CrmTagColor::tryFrom((string) $tag->color)?->getLabel() ?? CrmTagColor::Gray->getLabel())
                ->implode(', '),
            $this->text($account->notes),
        ];

        foreach ($this->metadataFields($book) as $field) {
            $row[] = $this->metadataValue(data_get($account->metadata, $field['key'] ?? ''), $field);
        }

        return $row;
    }

    /**
     * @return list<string>
     */
    private function opportunityRow(CrmOpportunity $opportunity): array
    {
        return [
            $opportunity->reference,
            $opportunity->title,
            $this->text($opportunity->account?->name),
            $this->text($opportunity->contact?->fullName()),
            $opportunity->stage->getLabel(),
            $opportunity->amount_estimated !== null
                ? number_format((float) $opportunity->amount_estimated, 2, ',', '')
                : '',
            $opportunity->probability !== null ? (string) $opportunity->probability : '',
            $opportunity->expected_close_date?->format('d/m/Y') ?? '',
            $this->sourceLabel($opportunity->source),
            $this->text($opportunity->owner?->name),
            $opportunity->won_at?->format('d/m/Y H:i') ?? '',
            $opportunity->closed_at?->format('d/m/Y H:i') ?? '',
            $this->text($opportunity->lost_reason),
            $this->plainText($opportunity->description),
            $opportunity->created_at?->format('d/m/Y H:i') ?? '',
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string>>  $rows
     */
    private function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';', '"', '\\', "\r\n");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';', '"', '\\', "\r\n");
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function metadataFields(CrmBook $book): array
    {
        /** @var array<int, array<string, mixed>> $fields */
        $fields = config('crm.fields.'.$book->value, []);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function metadataValue(mixed $value, array $field): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (($field['type'] ?? null) === 'select') {
            /** @var array<string, string> $options */
            $options = $field['options'] ?? [];

            return is_scalar($value)
                ? ($options[(string) $value] ?? (string) $value)
                : '';
        }

        return $this->text($value);
    }

    private function sourceLabel(mixed $source): string
    {
        if ($source instanceof CrmSource) {
            return $source->getLabel();
        }

        return CrmSource::tryFrom((string) $source)?->getLabel() ?? $this->text($source);
    }

    private function text(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function plainText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
