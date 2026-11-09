<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('food') && ! Schema::hasColumn('food', 'ingredients')) {
            Schema::table('food', function (Blueprint $table) {
                $table->text('ingredients')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('food') && Schema::hasColumn('food', 'ingredients')) {
            Schema::table('food', function (Blueprint $table) {
                $table->dropColumn('ingredients');
            });
        }
    }
};
