<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->json('decrypted_payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_webhook_logs');
    }
};
