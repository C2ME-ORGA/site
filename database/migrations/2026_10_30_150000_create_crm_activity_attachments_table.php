<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activity_attachments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('crm_activity_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('client_uuid')->nullable()->unique();

            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activity_attachments');
    }
};
