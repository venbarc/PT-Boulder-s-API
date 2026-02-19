<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pte_master_users', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 64)->unique();
            $table->string('pte_user_id')->nullable()->index();
            $table->string('full_name')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('role')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('status')->nullable()->index();
            $table->boolean('is_active')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_master_users');
    }
};
