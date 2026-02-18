<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_demographics', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_patient_id')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('date_of_birth')->nullable();
            $table->unsignedSmallInteger('month_of_birth')->nullable();
            $table->unsignedSmallInteger('year_of_birth')->nullable()->index();
            $table->string('phone_number')->nullable();
            $table->string('zip_code')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('insurance_info')->nullable();
            $table->text('open_case_str')->nullable();
            $table->text('close_case_str')->nullable();
            $table->string('dependent_of_id')->nullable()->index();
            $table->string('dependent_of_first_name')->nullable();
            $table->string('dependent_of_last_name')->nullable();
            $table->string('dependent_of_middle_name')->nullable();
            $table->string('dependent_of_email')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_demographics');
    }
};
