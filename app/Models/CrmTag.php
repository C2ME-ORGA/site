<?php

namespace App\Models;

use Database\Factories\CrmTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class CrmTag extends Model
{
    /** @use HasFactory<CrmTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'kind',
    ];

    protected static function booted(): void
    {
        static::saving(function (CrmTag $tag): void {
            $tag->slug = Str::slug($tag->name);
        });
    }

    /**
     * @return MorphToMany<CrmAccount, $this>
     */
    public function accounts(): MorphToMany
    {
        return $this->morphedByMany(CrmAccount::class, 'taggable', 'crm_taggables');
    }
}
