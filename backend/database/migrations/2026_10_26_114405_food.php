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
        Schema::create('food', function (Blueprint $table) {
            $table->id('food_id');
            $table->string('uploaded_by')->nullable();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->decimal('calories', 10, 2)->nullable();
            $table->decimal('protein', 10, 2)->nullable();
            $table->decimal('carbohydrates', 10, 2)->nullable();
            $table->decimal('fat', 10, 2)->nullable();
            $table->string('serving_size')->nullable();
            $table->string('serving_unit')->nullable();
            $table->timestamps();
        });

        Schema::create('meals', function (Blueprint $table) {
            $table->id('meal_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('meal_type');
            $table->date('meal_date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('meal_items', function (Blueprint $table) {
            $table->id('meal_item_id');
            $table->foreignId('meal_id')->constrained('meals', 'meal_id')->onDelete('cascade');
            $table->foreignId('food_id')->constrained('food', 'food_id')->onDelete('cascade');
            $table->integer('quantity')->nullable();
            $table->decimal('calories', 10, 2)->nullable();
            $table->decimal('protein', 10, 2)->nullable();
            $table->decimal('carbohydrates', 10, 2)->nullable();
            $table->decimal('fat', 10, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            
        });

        Schema::create('nutrition_goal', function (Blueprint $table) {
            $table->id('goal_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->decimal('daily_calories', 10, 2)->nullable();
            $table->decimal('daily_protein', 10, 2)->nullable();
            $table->decimal('daily_carbohydrates', 10, 2)->nullable();
            $table->decimal('daily_fat', 10, 2)->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food');
        Schema::dropIfExists('meals');
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('nutrition_goal');
    }
};
