<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pte_id')->unique()->comment('PtEverywhere patient ID');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->nullable();
            $table->json('raw_data')->nullable()->comment('Full API response for this record');
            $table->timestamp('pte_created_at')->nullable();
            $table->timestamp('pte_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_patients');
    }
};
