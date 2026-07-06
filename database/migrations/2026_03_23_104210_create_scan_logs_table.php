<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id('scanLogId');
            $table->foreignId('eventId')->nullable()->constrained('events', 'eventId')->nullOnDelete();
            $table->foreignId('mealSessionId')->nullable()->constrained('meal_sessions', 'mealSessionId')->nullOnDelete();
            $table->foreignId('passId')->nullable()->constrained('event_passes', 'passId')->nullOnDelete();
            $table->string('token');
            $table->enum('scanResult', [
                'valid',
                'invalid',
                'already_redeemed',
                'outside_window',
                'void',
                'no_active_meal',
            ]);
            $table->string('message')->nullable();
            $table->foreignId('scannedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deviceName')->nullable();
            $table->ipAddress('ipAddress')->nullable();
            $table->timestamps();

            $table->index(['mealSessionId', 'scanResult']);
            $table->index(['eventId', 'scanResult']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};