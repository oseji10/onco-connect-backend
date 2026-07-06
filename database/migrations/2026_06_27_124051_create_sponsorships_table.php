<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->bigIncrements('sponsorshipId');
            $table->unsignedBigInteger('eventId')->nullable();
            $table->string('type')->default('Sponsor'); // Sponsor | Partner
            $table->string('organizationName');
            $table->string('website')->nullable();
            $table->string('logoUrl')->nullable();
            $table->text('description')->nullable();
            $table->string('tier')->nullable();
            $table->string('status')->default('prospect');
            $table->string('currency', 8)->default('NGN');
            $table->decimal('agreedAmount', 14, 2)->nullable();
            $table->decimal('amountPaid', 14, 2)->nullable();
            $table->string('paymentStatus')->default('unpaid');
            $table->string('invoiceNumber')->nullable();
            $table->date('invoiceDate')->nullable();
            $table->unsignedBigInteger('createdBy')->nullable();
            $table->timestamps();

            $table->index('eventId');
            $table->index('status');

            // Optional FKs — adjust/remove if your key types differ.
            $table->foreign('createdBy')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sponsorship_contacts', function (Blueprint $table) {
            $table->bigIncrements('contactId');
            $table->unsignedBigInteger('sponsorshipId');
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->foreign('sponsorshipId')->references('sponsorshipId')->on('sponsorships')->cascadeOnDelete();
        });

        Schema::create('sponsorship_deliverables', function (Blueprint $table) {
            $table->bigIncrements('deliverableId');
            $table->unsignedBigInteger('sponsorshipId');
            $table->string('title');
            $table->string('status')->default('pending'); // pending | in_progress | fulfilled
            $table->date('dueDate')->nullable();
            $table->timestamps();

            $table->foreign('sponsorshipId')->references('sponsorshipId')->on('sponsorships')->cascadeOnDelete();
        });

        Schema::create('sponsorship_documents', function (Blueprint $table) {
            $table->bigIncrements('documentId');
            $table->unsignedBigInteger('sponsorshipId');
            $table->string('title');
            $table->string('category')->default('Other');
            $table->string('fileUrl')->nullable();
            $table->timestamps();

            $table->foreign('sponsorshipId')->references('sponsorshipId')->on('sponsorships')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_documents');
        Schema::dropIfExists('sponsorship_deliverables');
        Schema::dropIfExists('sponsorship_contacts');
        Schema::dropIfExists('sponsorships');
    }
};