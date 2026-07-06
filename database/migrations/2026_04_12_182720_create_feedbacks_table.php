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
        Schema::create('feedbacks', function (Blueprint $table) {
    $table->id();

    $table->tinyInteger('overall_rating')->nullable();
    $table->tinyInteger('organization')->nullable();
    $table->tinyInteger('communication')->nullable();
    $table->enum('respected', ['yes', 'no', 'somewhat'])->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
