<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quote_id')
                ->constrained('quotes')
                ->cascadeOnDelete();

            $table->unsignedInteger('position')->default(0);
            $table->string('code', 32)->nullable();
            $table->string('designation');
            $table->text('description')->nullable();

            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 20)->default('u');

            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('total_ht', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
