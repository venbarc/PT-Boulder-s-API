<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_provider_revenues', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_patient_id')->nullable()->index();
            $table->string('patient_name')->nullable();
            $table->string('therapist_name')->nullable()->index();
            $table->string('location_name')->nullable()->index();
            $table->string('patient_email')->nullable();
            $table->date('date_of_service')->nullable()->index();
            $table->decimal('revenue', 12, 2)->nullable();
            $table->decimal('adjustment', 12, 2)->nullable();
            $table->decimal('collected', 12, 2)->nullable();
            $table->decimal('due_amount', 12, 2)->nullable();
            $table->string('cpt')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_provider_revenues');
    }
};
