<?php

use App\Enums\QuoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();

            $table->string('kind', 20);
            $table->string('status', 20)->default(QuoteStatus::initial()->value);

            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->text('message')->nullable();

            /**
             * Charge utile propre à chaque parcours (matière, épaisseur,
             * dimensions, options…). Le détail varie selon le type de
             * demande ; conserver une colonne JSON évite d'aligner deux
             * parcours sur le même schéma rigide.
             */
            $table->json('payload')->nullable();

            $table->text('note')->nullable();
            $table->timestamp('quoted_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referer')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('kind');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
