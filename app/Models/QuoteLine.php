<?php

namespace App\Models;

use App\Observers\QuoteLineObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([QuoteLineObserver::class])]
class QuoteLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'quote_id',
        'position',
        'code',
        'designation',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'total_ht',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'total_ht' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
