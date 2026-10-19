<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->foreignId('crm_account_id')->nullable()->after('id')
                ->constrained('crm_accounts')->nullOnDelete();

            $table->foreignId('crm_contact_id')->nullable()->after('crm_account_id')
                ->constrained('crm_contacts')->nullOnDelete();

            $table->foreignId('crm_opportunity_id')->nullable()->after('crm_contact_id')
                ->constrained('crm_opportunities')->nullOnDelete();

            $table->foreignId('owner_id')->nullable()->after('crm_opportunity_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('contact_requests', function (Blueprint $table) {
            $table->foreignId('crm_account_id')->nullable()->after('id')
                ->constrained('crm_accounts')->nullOnDelete();

            $table->foreignId('crm_contact_id')->nullable()->after('crm_account_id')
                ->constrained('crm_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('crm_opportunity_id');
            $table->dropConstrainedForeignId('crm_contact_id');
            $table->dropConstrainedForeignId('crm_account_id');
        });

        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_contact_id');
            $table->dropConstrainedForeignId('crm_account_id');
        });
    }
};
