<?php

namespace App\Contracts;

use App\Models\EmailMailbox;
use App\Support\Mail\InboundEmail;

interface InboundFetcher
{
    /**
     * Récupère les messages entrants d'une boîte, du plus ancien au plus récent.
     *
     * @return list<InboundEmail>
     */
    public function fetch(EmailMailbox $mailbox): array;
}
