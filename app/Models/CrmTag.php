<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class CrmTag extends Model
{
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
