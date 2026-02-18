<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_patient_reports', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_patient_id')->nullable()->index();
            $table->string('patient_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('registration_date_str')->nullable();
            $table->string('registration_by')->nullable();
            $table->string('first_login_date_str')->nullable();
            $table->string('last_login_date_str')->nullable();
            $table->unsignedInteger('total_last_logins')->nullable();
            $table->string('first_appointment_id')->nullable()->index();
            $table->string('first_appointment_start_date')->nullable();
            $table->string('last_appointment_id')->nullable()->index();
            $table->string('last_appointment_start_date')->nullable();
            $table->string('next_appointment_id')->nullable()->index();
            $table->string('next_appointment_start_date')->nullable();
            $table->string('last_therapist_id')->nullable()->index();
            $table->string('last_therapist_name')->nullable();
            $table->string('last_therapist_email')->nullable();
            $table->json('package_membership')->nullable();
            $table->string('first_appointment_location')->nullable();
            $table->string('first_seen_by')->nullable();
            $table->string('last_seen_by')->nullable();
            $table->string('referred_by_name')->nullable();
            $table->string('payers_name')->nullable();
            $table->decimal('total_revenue', 12, 2)->nullable();
            $table->decimal('total_collected', 12, 2)->nullable();
            $table->string('status')->nullable()->index();
            $table->string('dependent_of_id')->nullable()->index();
            $table->string('dependent_of_first_name')->nullable();
            $table->string('dependent_of_last_name')->nullable();
            $table->string('dependent_of_middle_name')->nullable();
            $table->string('dependent_of_email')->nullable();
            $table->unsignedInteger('total_appointment_visit')->nullable();
            $table->unsignedInteger('total_session_completed')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_patient_reports');
    }
};
