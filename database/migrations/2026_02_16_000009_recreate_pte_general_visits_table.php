<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate table to align with report/general-visit success payload.
        Schema::dropIfExists('pte_general_visits');

        Schema::create('pte_general_visits', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_visit_id')->nullable()->index();
            $table->string('pte_patient_id')->nullable()->index();
            $table->string('patient_full_name')->nullable();
            $table->string('patient_first_name')->nullable();
            $table->string('patient_last_name')->nullable();
            $table->string('patient_email')->nullable();
            $table->string('patient_code')->nullable();
            $table->unsignedInteger('patient_total_appointment_visit')->nullable();
            $table->date('date_of_service')->nullable()->index();
            $table->string('appointment_status')->nullable()->index();
            $table->string('provider_id')->nullable()->index();
            $table->string('provider_name')->nullable()->index();
            $table->string('service_id')->nullable()->index();
            $table->string('service_name')->nullable()->index();
            $table->string('location_id')->nullable()->index();
            $table->string('location_name')->nullable()->index();
            $table->string('invoice_status')->nullable()->index();
            $table->string('invoice_number')->nullable()->index();
            $table->string('current_responsibility')->nullable();
            $table->string('package_invoice_number')->nullable()->index();
            $table->string('package_invoice_name')->nullable();
            $table->text('claim_created_info')->nullable();
            $table->decimal('charges', 12, 2)->nullable();
            $table->decimal('payments', 12, 2)->nullable();
            $table->decimal('units', 10, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->text('reason')->nullable();
            $table->string('last_update_by')->nullable();
            $table->dateTime('last_update_date')->nullable();
            $table->text('cancellation_notice')->nullable();
            $table->string('treatment_note_id')->nullable()->index();
            $table->string('treatment_note_number')->nullable()->index();
            $table->unsignedInteger('summary_total_appointments')->nullable();
            $table->decimal('summary_total_charges', 12, 2)->nullable();
            $table->decimal('summary_total_payments', 12, 2)->nullable();
            $table->decimal('summary_total_units', 12, 2)->nullable();
            $table->unsignedInteger('summary_total_patients')->nullable();
            $table->decimal('summary_average_charges', 12, 2)->nullable();
            $table->decimal('summary_average_payments', 12, 2)->nullable();
            $table->decimal('summary_average_units', 12, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_general_visits');
    }
};
