<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meal_sessions', function (Blueprint $table) {
            $table->id('mealSessionId');
            $table->foreignId('eventId')->constrained('events', 'eventId')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->date('mealDate');
            $table->time('startTime');
            $table->time('endTime');
            $table->string('location')->nullable();
            $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('draft');
            $table->unsignedInteger('sortOrder')->default(0);
            $table->unsignedInteger('redeemedCount')->default(0);
            $table->timestamps();

            $table->index(['eventId', 'mealDate']);
            $table->index(['eventId', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_sessions');
    }
};