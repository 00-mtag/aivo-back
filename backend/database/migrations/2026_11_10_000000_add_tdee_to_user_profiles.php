<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_profiles', 'tdee')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->string('tdee')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_profiles', 'tdee')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->dropColumn('tdee');
            });
        }
    }
};
