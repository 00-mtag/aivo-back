<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('food_allergies')) {
            Schema::create('food_allergies', function (Blueprint $table) {
                $table->id('allergy_id');
                $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
                $table->string('allergy_name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('health_conditions')) {
            Schema::create('health_conditions', function (Blueprint $table) {
                $table->id('condition_id');
                $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
                $table->string('condition_name')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_conditions');
        Schema::dropIfExists('food_allergies');
    }
};
