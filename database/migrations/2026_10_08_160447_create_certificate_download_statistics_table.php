<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_downloads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certificateId')->index();
            $table->unsignedBigInteger('attendeeId')->index();
            $table->unsignedBigInteger('eventId')->index();
            $table->string('type', 50);              // attendance | oral_presenter | poster_presenter
            $table->string('source', 20);            // public (email + phone page) | participant (logged in)
            $table->string('ipAddress', 45)->nullable();
            $table->string('userAgent', 255)->nullable();
            $table->timestamps();                    // created_at = when it was downloaded
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_downloads');
    }
};