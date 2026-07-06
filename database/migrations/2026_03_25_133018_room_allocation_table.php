<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_allocations', function (Blueprint $table) {
            $table->id('allocationId');

            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('attendeeId');
            $table->unsignedBigInteger('roomId');

            $table->enum('allocationType', ['initial', 'reallocation'])->default('initial');
            $table->enum('status', ['active', 'moved'])->default('active');

            $table->text('reason')->nullable(); // required for reallocation
            $table->timestamp('allocatedAt')->nullable();
            $table->unsignedBigInteger('allocatedBy')->nullable();

            $table->unsignedBigInteger('previousAllocationId')->nullable();
            $table->string('hotel')->nullable();
            $table->string('roomNumber')->nullable();
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
            $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->cascadeOnDelete();
            $table->foreign('roomId')->references('roomId')->on('rooms')->cascadeOnDelete();
            $table->foreign('allocatedBy')->references('id')->on('users')->nullOnDelete();
            $table->foreign('previousAllocationId')->references('allocationId')->on('room_allocations')->nullOnDelete();

            $table->index(['eventId', 'attendeeId']);
            $table->index(['eventId', 'roomId']);
            $table->index(['eventId', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_allocations');
    }
};