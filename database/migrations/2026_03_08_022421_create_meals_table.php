<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
Schema::create('meals', function (Blueprint $table) {
    $table->id('mealId');
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->date('mealDate');
    $table->time('startTime');
    $table->time('endTime');
    $table->string('location')->nullable();
    $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('draft');
    $table->unsignedInteger('ticketCount')->default(0);
    $table->unsignedInteger('redeemedCount')->default(0);
    $table->unsignedBigInteger('createdBy')->nullable();
    $table->timestamps();
    $table->foreign('createdBy')->references('id')->on('users')->onDeleteCascade();
});
            }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};