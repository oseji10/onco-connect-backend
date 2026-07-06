<?php
// database/migrations/2026_01_01_create_resources_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->bigInteger('file_size');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->integer('downloads')->default(0);
            
            // Relationships
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index(['status', 'category']);
            $table->index('uploaded_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};