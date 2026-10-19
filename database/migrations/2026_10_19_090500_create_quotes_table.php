<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();
            $table->unsignedInteger('version')->default(1);

            $table->foreignId('crm_opportunity_id')
                ->nullable()
                ->constrained('crm_opportunities')
                ->nullOnDelete();

            $table->foreignId('crm_account_id')
                ->constrained('crm_accounts')
                ->cascadeOnDelete();

            $table->foreignId('crm_contact_id')
                ->nullable()
                ->constrained('crm_contacts')
                ->nullOnDelete();

            $table->foreignId('quote_request_id')
                ->nullable()
                ->constrained('quote_requests')
                ->nullOnDelete();

            $table->string('status', 20);

            $table->string('subject');
            $table->text('notes')->nullable();

            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable();

            $table->decimal('amount_ht', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('amount_ttc', 12, 2)->default(0);

            $table->string('pdf_path')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('refused_at')->nullable();

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('owner_id');
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
