<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_profiles', 'birth_date')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->date('birth_date')->nullable();
            });
        }

        if (! Schema::hasColumn('user_profiles', 'experience_level')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->string('experience_level')->nullable();
            });
        }

        if (! Schema::hasColumn('user_profiles', 'profile_picture_mime')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $table->string('profile_picture_mime', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['profile_picture_mime', 'experience_level', 'birth_date'] as $column) {
            if (Schema::hasColumn('user_profiles', $column)) {
                Schema::table('user_profiles', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
