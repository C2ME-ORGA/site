<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tags', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tags', function (Blueprint $table): void {
            $table->string('kind', 20)->default('libre')->after('color');
        });
    }
};
