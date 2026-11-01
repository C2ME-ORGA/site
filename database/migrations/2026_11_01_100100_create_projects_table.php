<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('project_category_id')
                ->nullable()
                ->constrained('project_categories')
                ->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('client')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('description')->nullable();

            /*
             * Prestations associées au chantier (charpente métallique,
             * bardage, couverture…), affichées en étiquettes sur le site.
             */
            $table->json('services')->nullable();

            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['is_published', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
