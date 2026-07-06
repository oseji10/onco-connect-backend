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
        Schema::create('incidents', function (Blueprint $table) {
    $table->id('incidentId');
    $table->unsignedBigInteger('eventId');
    $table->string('incidentCode')->unique();
    $table->string('title');
    $table->text('description');
    $table->enum('category', [
        'medical',
        'security',
        'misconduct',
        'room',
        'lost_found',
        'access',
        'attendance',
        'facility',
        'other',
    ]);
    $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
    $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'escalated'])->default('open');
    $table->unsignedBigInteger('reportedBy');
    $table->unsignedBigInteger('assignedTo')->nullable();
    $table->unsignedBigInteger('attendeeId')->nullable();
    $table->unsignedBigInteger('roomId')->nullable();
    $table->string('location')->nullable();
    $table->timestamp('occurredAt')->nullable();
    $table->timestamp('reportedAt')->nullable();
    $table->timestamp('resolvedAt')->nullable();
    $table->text('resolutionNote')->nullable();
    $table->boolean('isAnonymous')->default(false);
    $table->timestamps();

    $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
    $table->foreign('reportedBy')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('assignedTo')->references('id')->on('users')->nullOnDelete();
    $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->nullOnDelete();
    $table->foreign('roomId')->references('roomId')->on('rooms')->nullOnDelete();

    $table->index(['eventId', 'status']);
    $table->index(['eventId', 'category']);
    $table->index(['eventId', 'severity']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
