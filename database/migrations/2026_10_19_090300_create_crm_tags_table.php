<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->nullable();
            $table->string('kind', 20)->default('libre');

            $table->timestamps();
        });

        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->foreignId('crm_tag_id')
                ->constrained('crm_tags')
                ->cascadeOnDelete();

            $table->morphs('taggable');

            $table->primary(['crm_tag_id', 'taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_taggables');
        Schema::dropIfExists('crm_tags');
    }
};
