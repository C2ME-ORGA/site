<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();

            $table->foreignId('crm_account_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('crm_contact_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->unsignedSmallInteger('reminder_minutes')->nullable();

            $table->string('recurrence', 16)->nullable();
            $table->date('recurrence_until')->nullable();

            $table->boolean('share_with_all')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
            $table->index(['share_with_all', 'starts_at']);
        });

        Schema::create('calendar_event_user', function (Blueprint $table): void {
            $table->foreignId('calendar_event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->primary(['calendar_event_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_user');
        Schema::dropIfExists('calendar_events');
    }
};
