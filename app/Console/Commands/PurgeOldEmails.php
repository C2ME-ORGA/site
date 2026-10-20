<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

#[Signature('crm:purge-emails')]
#[Description('Supprime les messages et pièces jointes au-delà de la durée de conservation.')]
class PurgeOldEmails extends Command
{
    public function handle(): int
    {
        $months = (int) config('crm.email.retention_months', 36);
        $cutoff = now()->subMonths($months);

        $messages = 0;
        $files = 0;

        EmailMessage::query()
            ->where(fn (Builder $query) => $query
                ->where('received_at', '<', $cutoff)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('received_at')
                    ->where('created_at', '<', $cutoff)))
            ->with('attachments')
            ->chunkById(100, function ($batch) use (&$messages, &$files): void {
                foreach ($batch as $message) {
                    foreach ($message->attachments as $attachment) {
                        Storage::disk('local')->delete($attachment->path);

                        $files++;
                    }

                    $message->delete();

                    $messages++;
                }
            });

        $this->info(sprintf(
            '%d message(s) supprimé(s), %d pièce(s) jointe(s) purgée(s) — conservation de %d mois.',
            $messages,
            $files,
            $months
        ));

        return self::SUCCESS;
    }
}
