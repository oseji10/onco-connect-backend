<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meal_redemptions', function (Blueprint $table) {
            $table->id('redemptionId');
            $table->foreignId('mealSessionId')->constrained('meal_sessions', 'mealSessionId')->cascadeOnDelete();
            $table->foreignId('passId')->constrained('event_passes', 'passId')->cascadeOnDelete();
            $table->foreignId('redeemedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deviceName')->nullable();
            $table->timestamp('redeemedAt');
            $table->timestamps();

            $table->unique(['mealSessionId', 'passId']);
            $table->index(['mealSessionId', 'redeemedAt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_redemptions');
    }
};