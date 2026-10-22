<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->index(['status', 'accepted_at'], 'quotes_status_accepted_at_index');
            $table->index('sent_at', 'quotes_sent_at_index');
            $table->index(['status', 'valid_until'], 'quotes_status_valid_until_index');
        });

        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->index('won_at', 'crm_opportunities_won_at_index');
        });

        Schema::table('crm_accounts', function (Blueprint $table): void {
            $table->index('book', 'crm_accounts_book_index');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex('quotes_status_accepted_at_index');
            $table->dropIndex('quotes_sent_at_index');
            $table->dropIndex('quotes_status_valid_until_index');
        });

        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropIndex('crm_opportunities_won_at_index');
        });

        Schema::table('crm_accounts', function (Blueprint $table): void {
            $table->dropIndex('crm_accounts_book_index');
        });
    }
};
