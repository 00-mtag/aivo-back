<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->string('profile_picture')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->text('dietary_preferences')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
