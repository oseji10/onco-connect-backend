<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meal_tickets', function (Blueprint $table) {
    $table->id('mealTicketId');
    $table->unsignedBigInteger('mealId')->nullable();
    $table->string('token')->unique();
    $table->string('serialNumber')->nullable()->unique();
    $table->text('qrPayload')->nullable();
    $table->enum('status', ['unused', 'redeemed', 'void'])->default('unused');
    $table->timestamp('redeemedAt')->nullable();
    $table->unsignedBigInteger('redeemedBy')->nullable();
    $table->timestamp('lastCcannedAt')->nullable();
    $table->timestamps();

    
    $table->foreign('mealId')->references('mealId')->on('meals')->onDeleteCascade();
    $table->foreign('redeemedBy')->references('id')->on('users')->onDeleteCascade();

            
    
});
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_tickets');
    }
};