<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_services', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_service_id')->nullable()->index();
            $table->string('service_name')->nullable()->index();
            $table->string('service_code')->nullable()->index();
            $table->string('cpt_code')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('tax', 12, 2)->nullable();
            $table->boolean('is_active')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_services');
    }
};
