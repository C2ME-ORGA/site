<?php

namespace App\Services;

use App\Contracts\InboundFetcher;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Support\Mail\InboundAttachment;
use App\Support\Mail\InboundEmail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MailboxSynchronizer
{
    public function __construct(private readonly InboundFetcher $fetcher) {}

    /**
     * Importe les nouveaux messages d'une boîte et met à jour son curseur.
     *
     * @return int Nombre de messages importés.
     */
    public function synchronize(EmailMailbox $mailbox): int
    {
        try {
            $messages = $this->fetcher->fetch($mailbox);
        } catch (Throwable $exception) {
            $mailbox->forceFill([
                'last_synced_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->saveQuietly();

            return 0;
        }

        $batch = array_slice($messages, 0, (int) config('crm.email.sync_batch_size', 50));

        $imported = 0;
        $lastUid = (int) ($mailbox->last_uid ?? 0);

        foreach ($batch as $message) {
            if ($message->fromEmail === '') {
                continue;
            }

            $this->store($mailbox, $message);
            $imported++;

            $lastUid = max($lastUid, (int) $message->uid);
        }

        $mailbox->forceFill([
            'last_uid' => $lastUid > 0 ? (string) $lastUid : $mailbox->last_uid,
            'last_synced_at' => now(),
            'last_error' => null,
        ])->saveQuietly();

        return $imported;
    }

    private function store(EmailMailbox $mailbox, InboundEmail $message): void
    {
        [$account, $contact] = $this->match($message->fromEmail);

        $attributes = [
            'uid' => $message->uid,
            'from_email' => $message->fromEmail,
            'from_name' => $message->fromName,
            'to_recipients' => $message->to,
            'cc_recipients' => $message->cc,
            'subject' => $message->subject,
            'body_text' => $message->textBody,
            'body_html' => $message->htmlBody,
            'sent_at' => $message->date,
            'received_at' => $message->date ?? now(),
            'is_read' => $message->isRead,
            'is_sent' => false,
            'folder' => 'INBOX',
            'in_reply_to' => $message->inReplyTo,
            'references' => $message->references,
            'crm_account_id' => $account?->id,
            'crm_contact_id' => $contact?->id,
        ];

        $existing = $this->findExisting($mailbox, $message);

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();
            $this->storeAttachments($existing, $message->attachments);

            return;
        }

        $attributes['email_mailbox_id'] = $mailbox->id;
        $attributes['message_id'] = $message->messageId;

        $created = EmailMessage::create($attributes);
        $this->storeAttachments($created, $message->attachments);
    }

    /**
     * @param  list<InboundAttachment>  $attachments
     */
    private function storeAttachments(EmailMessage $message, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        foreach ($message->attachments as $existing) {
            Storage::disk('local')->delete($existing->path);
        }

        $message->attachments()->delete();

        foreach ($attachments as $attachment) {
            $path = sprintf(
                'mail-attachments/inbound/%d/%s',
                $message->id,
                Str::uuid()->toString(),
            );

            Storage::disk('local')->put($path, $attachment->contents);

            $message->attachments()->create([
                'filename' => $this->safeFilename($attachment->filename),
                'mime_type' => $attachment->mimeType,
                'size' => $attachment->size,
                'path' => $path,
            ]);
        }
    }

    private function safeFilename(string $filename): string
    {
        $name = preg_replace('/[^\pL\pN._ -]+/u', '_', basename($filename)) ?? 'piece-jointe';

        return trim($name) === '' ? 'piece-jointe' : $name;
    }

    private function findExisting(EmailMailbox $mailbox, InboundEmail $message): ?EmailMessage
    {
        $query = EmailMessage::query()->where('email_mailbox_id', $mailbox->id);

        if ($message->messageId !== null) {
            return $query->where('message_id', $message->messageId)->first();
        }

        return $query->where('uid', $message->uid)->first();
    }

    /**
     * @return array{0: CrmAccount|null, 1: CrmContact|null}
     */
    private function match(string $email): array
    {
        $normalized = mb_strtolower($email);

        $contact = CrmContact::query()
            ->whereRaw('lower(email) = ?', [$normalized])
            ->first();

        if ($contact !== null) {
            return [$contact->account, $contact];
        }

        $account = CrmAccount::query()
            ->whereRaw('lower(email) = ?', [$normalized])
            ->first();

        return [$account, null];
    }
}
