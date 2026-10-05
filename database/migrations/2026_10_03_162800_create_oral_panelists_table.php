<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oral_panelists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            // Secret that goes in the panelist's personal scoring link.
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oral_panelists');
    }
};