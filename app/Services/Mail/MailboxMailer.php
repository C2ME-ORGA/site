<?php

namespace App\Services\Mail;

use App\Enums\AuditEvent;
use App\Mail\OutboundMail;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MailboxMailer
{
    /**
     * Envoie un e-mail via le SMTP de la boîte et l'archive comme message sortant.
     *
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $attachments  chemins relatifs sur le disque « local »
     * @param  list<string>  $references  identifiants des messages cités
     */
    public function send(
        EmailMailbox $mailbox,
        array $to,
        string $subject,
        string $html,
        array $cc = [],
        ?string $inReplyTo = null,
        ?int $crmAccountId = null,
        ?int $crmContactId = null,
        array $attachments = [],
        array $references = [],
    ): EmailMessage {
        $mailer = 'mailbox_'.$mailbox->id;

        config()->set('mail.mailers.'.$mailer, [
            'transport' => 'smtp',
            'host' => $mailbox->smtp_host,
            'port' => $mailbox->smtp_port,
            'encryption' => $mailbox->smtp_encryption === 'none' ? null : $mailbox->smtp_encryption,
            'username' => $mailbox->smtp_username,
            'password' => $mailbox->smtp_password,
            'timeout' => null,
        ]);

        Mail::mailer($mailer)
            ->to($to)
            ->cc($cc)
            ->send(
                (new OutboundMail($subject, $html, $attachments, $inReplyTo, $references))
                    ->from($mailbox->email, $mailbox->name)
            );

        // Les fichiers ne servent qu'à l'envoi : on ne les conserve pas.
        if ($attachments !== []) {
            Storage::disk('local')->delete($attachments);
        }

        $message = EmailMessage::create([
            'email_mailbox_id' => $mailbox->id,
            'from_email' => $mailbox->email,
            'from_name' => $mailbox->name,
            'to_recipients' => $this->recipients($to),
            'cc_recipients' => $this->recipients($cc),
            'subject' => $subject,
            'body_html' => $html,
            'sent_at' => now(),
            'received_at' => now(),
            'is_read' => true,
            'is_sent' => true,
            'folder' => 'Sent',
            'in_reply_to' => $inReplyTo,
            'references' => $references === [] ? null : implode(' ', $references),
            'crm_account_id' => $crmAccountId,
            'crm_contact_id' => $crmContactId,
        ]);

        app(AuditLogger::class)->record($message, AuditEvent::Sent, [
            'new' => [
                'destinataires' => implode(', ', $to),
                'copie' => $cc === [] ? null : implode(', ', $cc),
                'objet' => $subject,
            ],
        ]);

        return $message;
    }

    /**
     * @param  list<string>  $emails
     * @return list<array{email: string, name: string|null}>
     */
    private function recipients(array $emails): array
    {
        return array_map(
            fn (string $email): array => ['email' => $email, 'name' => null],
            $emails,
        );
    }
}
