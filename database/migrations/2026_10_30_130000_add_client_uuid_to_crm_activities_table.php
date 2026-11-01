<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
