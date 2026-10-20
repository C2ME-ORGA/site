<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $email_message_id
 * @property string $filename
 * @property string|null $mime_type
 * @property int $size
 * @property string $path
 */
class EmailAttachment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email_message_id',
        'filename',
        'mime_type',
        'size',
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EmailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
