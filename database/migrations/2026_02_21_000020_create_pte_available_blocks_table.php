<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_available_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_therapist_id')->nullable()->index();
            $table->dateTime('start_datetime')->nullable()->index();
            $table->dateTime('end_datetime')->nullable()->index();
            $table->string('location_id')->nullable()->index();
            $table->string('location_name')->nullable();
            $table->string('service_id')->nullable()->index();
            $table->string('service_name')->nullable();
            $table->date('request_start_date')->nullable()->index();
            $table->date('request_end_date')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_available_blocks');
    }
};
