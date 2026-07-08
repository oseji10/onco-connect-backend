<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstract_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abstract_id')->constrained('abstracts')->cascadeOnDelete();
            $table->string('name');
            $table->string('affiliation');
            $table->string('email')->nullable();
            $table->boolean('is_corresponding')->default(false);
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abstract_authors');
    }
};