<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_passes', function (Blueprint $table) {
            $table->id('passId');
            $table->foreignId('eventId')->constrained('events', 'eventId')->cascadeOnDelete();
            $table->string('passCode')->unique();
            $table->string('serialNumber')->unique()->nullable();
            $table->text('qrPayload')->nullable();
            $table->string('qrPath')->nullable();
            $table->string('qrUrl')->nullable();
            $table->enum('status', ['active', 'void'])->default('active');
            $table->timestamps();

            $table->index(['eventId', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_passes');
    }
};