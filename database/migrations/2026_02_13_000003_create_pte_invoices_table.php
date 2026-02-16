<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pte_id')->unique()->comment('PtEverywhere invoice ID');
            $table->unsignedBigInteger('pte_patient_id')->nullable()->index();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_invoices');
    }
};
