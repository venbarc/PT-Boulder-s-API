<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pte_webhook_logs');
        Schema::dropIfExists('pte_invoices');
        Schema::dropIfExists('pte_appointments');
        Schema::dropIfExists('pte_patients');
    }

    public function down(): void
    {
        Schema::create('pte_patients', function (Blueprint $table) {
            $table->id();
            $table->string('pte_id', 64)->unique()->comment('PtEverywhere patient ID');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('pte_created_at')->nullable();
            $table->timestamp('pte_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pte_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('pte_id', 64)->unique()->comment('PtEverywhere appointment ID');
            $table->string('pte_patient_id', 64)->nullable()->index();
            $table->string('patient_name')->nullable();
            $table->string('service_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('provider_name')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('pte_created_at')->nullable();
            $table->timestamp('pte_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pte_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('pte_id', 64)->unique()->comment('PtEverywhere invoice ID');
            $table->string('pte_patient_id', 64)->nullable()->index();
            $table->string('patient_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('balance', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->json('line_items')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('pte_created_at')->nullable();
            $table->timestamp('pte_updated_at')->nullable();
            $table->timestamps();
        });

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
};
