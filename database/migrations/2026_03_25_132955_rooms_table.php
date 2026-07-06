<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id('roomId');
            $table->unsignedBigInteger('eventId');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('building')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->enum('gender', ['male', 'female', 'mixed'])->default('mixed');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
            $table->index(['eventId', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};