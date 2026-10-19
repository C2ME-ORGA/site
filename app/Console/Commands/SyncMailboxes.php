<?php

namespace App\Console\Commands;

use App\Models\EmailMailbox;
use App\Services\MailboxSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mailboxes:sync {--mailbox= : Identifiant d’une boîte à synchroniser}')]
#[Description('Récupère les nouveaux e-mails entrants des boîtes actives.')]
class SyncMailboxes extends Command
{
    public function handle(MailboxSynchronizer $synchronizer): int
    {
        $query = EmailMailbox::query()->where('is_active', true);

        $mailboxId = $this->option('mailbox');

        if ($mailboxId !== null && $mailboxId !== '') {
            $query->whereKey($mailboxId);
        }

        $total = 0;

        $query->each(function (EmailMailbox $mailbox) use ($synchronizer, &$total): void {
            $count = $synchronizer->synchronize($mailbox);
            $total += $count;

            $this->line(sprintf('%s : %d message(s).', $mailbox->email, $count));
        });

        $this->info(sprintf('%d message(s) importé(s).', $total));

        return self::SUCCESS;
    }
}
