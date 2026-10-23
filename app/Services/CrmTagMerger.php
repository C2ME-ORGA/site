<?php

namespace App\Services;

use App\Models\CrmTag;
use Illuminate\Support\Facades\DB;

/**
 * Fusionne des étiquettes : les étiquetages migrent vers la cible, puis les
 * sources sont supprimées.
 *
 * La table pivot `crm_taggables` porte une clé primaire composite
 * (crm_tag_id, taggable_type, taggable_id) : un compte déjà étiqueté de la
 * source et de la cible ne doit pas créer de doublon.
 */
final class CrmTagMerger
{
    /**
     * @param  list<CrmTag>  $sources
     */
    public function merge(CrmTag $target, array $sources): CrmTag
    {
        return DB::transaction(function () use ($target, $sources): CrmTag {
            foreach ($sources as $source) {
                if ($source->is($target)) {
                    continue;
                }

                $this->moveTaggables($source, $target);

                $source->delete();
            }

            return $target->refresh();
        });
    }

    private function moveTaggables(CrmTag $source, CrmTag $target): void
    {
        $rows = DB::table('crm_taggables')
            ->where('crm_tag_id', $source->getKey())
            ->get();

        foreach ($rows as $row) {
            $alreadyAttached = DB::table('crm_taggables')
                ->where('crm_tag_id', $target->getKey())
                ->where('taggable_type', $row->taggable_type)
                ->where('taggable_id', $row->taggable_id)
                ->exists();

            if ($alreadyAttached) {
                continue;
            }

            DB::table('crm_taggables')->insert([
                'crm_tag_id' => $target->getKey(),
                'taggable_type' => $row->taggable_type,
                'taggable_id' => $row->taggable_id,
            ]);
        }

        DB::table('crm_taggables')
            ->where('crm_tag_id', $source->getKey())
            ->delete();
    }
}
