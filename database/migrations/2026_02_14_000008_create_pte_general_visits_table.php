<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_general_visits', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_visit_id')->nullable()->index();
            $table->string('pte_patient_id')->nullable()->index();
            $table->string('patient_name')->nullable();
            $table->string('service_name')->nullable();
            $table->string('provider_name')->nullable()->index();
            $table->string('location_name')->nullable()->index();
            $table->date('date_of_service')->nullable()->index();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('appointment_status')->nullable()->index();
            $table->decimal('units', 10, 2)->nullable();
            $table->decimal('charges', 12, 2)->nullable();
            $table->decimal('payments', 12, 2)->nullable();
            $table->decimal('balance', 12, 2)->nullable();
            $table->string('invoice_number')->nullable()->index();
            $table->string('invoice_status')->nullable()->index();
            $table->string('current_responsibility')->nullable();
            $table->string('cpt_code')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_general_visits');
    }
};
