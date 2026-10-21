<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_follow_up_reminders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('crm_account_id')
                ->constrained('crm_accounts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('due_at');
            $table->string('stage', 20);

            $table->timestamp('database_sent_at')->nullable();
            $table->timestamp('mail_sent_at')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(
                ['crm_account_id', 'user_id', 'due_at', 'stage'],
                'follow_up_reminder_unique',
            );

            $table->index(['user_id', 'stage']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_follow_up_reminders');
    }
};
