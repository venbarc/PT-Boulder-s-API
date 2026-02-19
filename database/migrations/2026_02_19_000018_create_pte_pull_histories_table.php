<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_pull_histories', function (Blueprint $table) {
            $table->id();
            $table->string('command_name')->index();
            $table->string('source_key')->nullable()->index();
            $table->string('status')->default('running')->index();
            $table->string('triggered_by')->default('manual')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->date('from_date')->nullable()->index();
            $table->date('to_date')->nullable()->index();
            $table->unsignedSmallInteger('from_year')->nullable()->index();
            $table->unsignedSmallInteger('to_year')->nullable()->index();
            $table->json('options')->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('upserted_count')->default(0);
            $table->unsignedInteger('failed_chunks')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_pull_histories');
    }
};
