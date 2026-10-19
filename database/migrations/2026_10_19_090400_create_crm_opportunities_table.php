<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();

            $table->foreignId('crm_account_id')
                ->constrained('crm_accounts')
                ->cascadeOnDelete();

            $table->foreignId('crm_contact_id')
                ->nullable()
                ->constrained('crm_contacts')
                ->nullOnDelete();

            $table->foreignId('quote_request_id')
                ->nullable()
                ->unique()
                ->constrained('quote_requests')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('stage', 20);
            $table->decimal('amount_estimated', 12, 2)->nullable();
            $table->unsignedTinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('source', 32)->nullable();

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('lost_reason')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('stage');
            $table->index('owner_id');
            $table->index('expected_close_date');
            $table->index(['crm_account_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
