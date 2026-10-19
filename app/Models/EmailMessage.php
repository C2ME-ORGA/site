<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email_mailbox_id',
        'message_id',
        'in_reply_to',
        'references',
        'folder',
        'uid',
        'from_email',
        'from_name',
        'to_recipients',
        'cc_recipients',
        'subject',
        'body_text',
        'body_html',
        'sent_at',
        'received_at',
        'is_read',
        'is_sent',
        'crm_account_id',
        'crm_contact_id',
        'crm_opportunity_id',
        'quote_id',
        'raw_headers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'raw_headers' => 'array',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'is_read' => 'boolean',
            'is_sent' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<EmailMailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(EmailMailbox::class, 'email_mailbox_id');
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }

    /**
     * @return BelongsTo<CrmContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    /**
     * @return BelongsTo<CrmOpportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'crm_opportunity_id');
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
