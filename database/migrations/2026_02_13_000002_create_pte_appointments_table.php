<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pte_id')->unique()->comment('PtEverywhere appointment ID');
            $table->unsignedBigInteger('pte_patient_id')->nullable()->index();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_appointments');
    }
};
