<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('book', 32);
            $table->string('category', 32);

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('siret', 20)->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->string('website')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mobile', 32)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('FR');

            $table->string('status', 20);
            $table->string('source', 32)->nullable();
            $table->string('potential', 1)->nullable();

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();

            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['book', 'category']);
            $table->index('status');
            $table->index('owner_id');
            $table->index('next_follow_up_at');
            $table->index('city');
            $table->index('siret');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_accounts');
    }
};
