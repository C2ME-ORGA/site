<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('crm_account_id')
                ->nullable()
                ->constrained('crm_accounts')
                ->nullOnDelete();

            $table->foreignId('crm_contact_id')
                ->nullable()
                ->constrained('crm_contacts')
                ->nullOnDelete();

            $table->foreignId('crm_opportunity_id')
                ->nullable()
                ->constrained('crm_opportunities')
                ->nullOnDelete();

            $table->foreignId('quote_id')
                ->nullable()
                ->constrained('quotes')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type', 20);
            $table->string('subject');
            $table->text('body')->nullable();
            $table->text('outcome')->nullable();

            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('remind_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('due_at');
            $table->index('completed_at');
            $table->index('user_id');
            $table->index(['crm_account_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
