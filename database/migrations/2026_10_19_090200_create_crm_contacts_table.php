<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('crm_account_id')
                ->constrained('crm_accounts')
                ->cascadeOnDelete();

            $table->string('civility', 16)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('job_title')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mobile', 32)->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('decision_maker')->default(false);
            $table->boolean('do_not_contact')->default(false);

            $table->text('notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('crm_account_id');
            $table->index('email');
            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
