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
        Schema::create('progress_records', function (Blueprint $table) {
            $table->id('progress_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->date('record_date');
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('body_fat', 5, 2)->nullable();
            $table->decimal('muscle_mass', 10, 2)->nullable();
            $table->decimal('waist_circumference', 10, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('progress_workout_records', function (Blueprint $table) {
            $table->id('progress_workout_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('workout_id')->constrained('workouts', 'workout_id')->onDelete('cascade');
            $table->string('workout_name')->nullable();
            $table->string('workout_type')->nullable();
            $table->string('workout_duration')->nullable();
            $table->string('workout_intensity')->nullable();
            $table->string('exercises_done')->nullable();
            $table->string('exercises_skipped')->nullable();
            $table->timestamps();
        });

        Schema::create('calorie_burned_records', function (Blueprint $table) {
            $table->id('calorie_burned_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('workout_id')->constrained('workouts', 'workout_id')->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained('exercises', 'exercise_id')->onDelete('cascade');
            $table->string('calories_burned')->nullable();
            $table->timestamps();
        });
        
        Schema::create('calorie_intake_records', function (Blueprint $table) {
            $table->id('calorie_intake_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('meal_id')->constrained('meals', 'meal_id')->onDelete('cascade');
            $table->foreignId('food_id')->constrained('food', 'food_id')->onDelete('cascade');
            $table->string('calories_intake')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calorie_intake_records');
        Schema::dropIfExists('calorie_burned_records');
        Schema::dropIfExists('progress_workout_records');
        Schema::dropIfExists('progress_records');
    }
};
