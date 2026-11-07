<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id('profile_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->integer('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('activity_level')->nullable();
            $table->string('fitness_goal')->nullable();
            $table->string('target_weight')->nullable();
            $table->binary('profile_picture')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->text('dietary_preferences')->nullable();
            $table->timestamps();
        });
        Schema::create('food_allergies', function (Blueprint $table) {
            $table->id('allergy_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('allergy_name')->nullable();
            $table->timestamps();
        });

        Schema::create('health_conditions', function (Blueprint $table) {
            $table->id('condition_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('condition_name')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE user_profiles MODIFY profile_picture MEDIUMBLOB NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_conditions');
        Schema::dropIfExists('food_allergies');
        Schema::dropIfExists('user_profiles');
    }
};
