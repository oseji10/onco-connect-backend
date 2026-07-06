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
        Schema::create('staff_feedbacks', function (Blueprint $table) {
    $table->id();

    
    $table->unsignedBigInteger('feedback_id')->nullable();
     $table->unsignedBigInteger('staff_id')->nullable();

    $table->tinyInteger('performance')->nullable();
    $table->tinyInteger('approachability')->nullable();
    $table->tinyInteger('effectiveness')->nullable();

    $table->text('strength')->nullable();
    $table->text('improvement')->nullable();

    $table->timestamps();

    
    $table->foreign('feedback_id')->references('id')->on('feedbacks')->cascadeOnDelete();
    $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_feedbacks');
    }
};
