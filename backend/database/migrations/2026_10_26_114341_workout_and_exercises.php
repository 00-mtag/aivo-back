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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id('exercise_id');
            $table->string('name');
            $table->string('created_by')->nullable();
            $table->string('description')->nullable();
            $table->string('muscle_group')->nullable();
            $table->string('equipment')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('workouts', function (Blueprint $table) {
            $table->id('workout_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('duration')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
        
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id('workout_exercise_id');
            $table->foreignId('workout_id')->constrained('workouts', 'workout_id')->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained('exercises', 'exercise_id')->onDelete('cascade');
            $table->string('sets')->nullable();
            $table->string('reps')->nullable();
            $table->string('weight')->nullable();
            $table->string('rest_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('workouts');
        Schema::dropIfExists('workout_exercises');
    }
};
