<?php

namespace App\Services\Mail;

use App\Contracts\InboundFetcher;
use App\Models\EmailMailbox;
use App\Support\Mail\InboundEmail;
use Carbon\CarbonImmutable;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class ImapInboundFetcher implements InboundFetcher
{
    /**
     * @return list<InboundEmail>
     */
    public function fetch(EmailMailbox $mailbox): array
    {
        $client = $this->client($mailbox);

        try {
            $client->connect();

            $folder = $client->getFolder('INBOX');

            if ($folder === null) {
                return [];
            }

            $messages = $folder->query()
                ->leaveUnread()
                ->fetchOrderAsc()
                ->getByUidGreater((int) ($mailbox->last_uid ?? 0));

            $inbound = [];

            foreach ($messages as $message) {
                $inbound[] = $this->toInbound($message);
            }

            return $inbound;
        } finally {
            $client->disconnect();
        }
    }

    private function client(EmailMailbox $mailbox): Client
    {
        $manager = new ClientManager;

        return $manager->make([
            'host' => $mailbox->imap_host,
            'port' => $mailbox->imap_port,
            'protocol' => 'imap',
            'encryption' => $mailbox->imap_encryption === 'none' ? false : $mailbox->imap_encryption,
            'validate_cert' => true,
            'username' => $mailbox->imap_username,
            'password' => $mailbox->imap_password,
            'authentication' => null,
        ]);
    }

    private function toInbound(Message $message): InboundEmail
    {
        $from = $this->addresses($message->get('from'));

        return new InboundEmail(
            uid: (string) $message->get('uid'),
            messageId: $this->cleanId($this->stringOrNull($message->get('message_id'))),
            inReplyTo: $this->cleanId($this->stringOrNull($message->get('in_reply_to'))),
            references: $this->stringOrNull($message->get('references')),
            fromEmail: $from[0]['email'] ?? '',
            fromName: $from[0]['name'] ?? null,
            to: $this->addresses($message->get('to')),
            cc: $this->addresses($message->get('cc')),
            subject: $this->stringOrNull($message->get('subject')),
            textBody: $this->stringOrNull($message->getTextBody()),
            htmlBody: $this->stringOrNull($message->getHTMLBody()),
            date: $this->date($message),
            isRead: $message->getFlags()->contains('seen'),
        );
    }

    /**
     * @return list<array{email: string, name: string|null}>
     */
    private function addresses(mixed $attribute): array
    {
        $values = $attribute instanceof Attribute ? $attribute->all() : $attribute;

        if ($values instanceof \Traversable) {
            $values = iterator_to_array($values);
        }

        if (! is_array($values)) {
            return [];
        }

        $addresses = [];

        foreach ($values as $address) {
            if ($address instanceof Address && $address->mail !== '') {
                $addresses[] = [
                    'email' => $address->mail,
                    'name' => $address->personal !== '' ? $address->personal : null,
                ];
            }
        }

        return $addresses;
    }

    private function date(Message $message): ?CarbonImmutable
    {
        try {
            $date = $message->get('date');

            if ($date instanceof Attribute && $date->count() > 0) {
                return CarbonImmutable::instance($date->toDate());
            }
        } catch (Throwable) {
            // Ignore les dates illisibles.
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value instanceof Attribute) {
            $value = $value->toString();
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function cleanId(?string $value): ?string
    {
        return $value !== null ? trim($value, "<> \t\n\r\0\x0B") : null;
    }
}
