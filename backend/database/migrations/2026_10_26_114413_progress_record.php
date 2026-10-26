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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_records');
    }
};
