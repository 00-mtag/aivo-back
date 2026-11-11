<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_items', function (Blueprint $table) {
            $table->decimal('quantity', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('meal_items', function (Blueprint $table) {
            $table->integer('quantity')->nullable()->change();
        });
    }
};
