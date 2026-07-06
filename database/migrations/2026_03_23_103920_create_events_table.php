<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id('eventId');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->date('startDate');
            $table->date('endDate');
            $table->string('location')->nullable();
            $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('draft');
            $table->unsignedInteger('passCount')->default(0);
            $table->foreignId('createdBy')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};