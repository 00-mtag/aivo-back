<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workout_exercises', 'day')) {
            Schema::table('workout_exercises', function (Blueprint $table) {
                $table->string('day')->nullable();
            });
        }

        if (! Schema::hasColumn('workout_exercises', 'notes')) {
            Schema::table('workout_exercises', function (Blueprint $table) {
                $table->string('notes')->nullable();
            });
        }

        if (! Schema::hasColumn('workout_exercises', 'muscle')) {
            Schema::table('workout_exercises', function (Blueprint $table) {
                $table->string('muscle')->nullable();
            });
        }

        if (! Schema::hasColumn('workout_exercises', 'sort_order')) {
            Schema::table('workout_exercises', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0);
            });
        }

        if (! Schema::hasColumn('workouts', 'active')) {
            Schema::table('workouts', function (Blueprint $table) {
                $table->boolean('active')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workouts', 'active')) {
            Schema::table('workouts', function (Blueprint $table) {
                $table->dropColumn('active');
            });
        }

        foreach (['notes', 'muscle', 'sort_order'] as $column) {
            if (Schema::hasColumn('workout_exercises', $column)) {
                Schema::table('workout_exercises', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
