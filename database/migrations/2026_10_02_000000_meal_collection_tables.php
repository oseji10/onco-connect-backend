<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('meal_sessions')) {
            Schema::create('meal_sessions', function (Blueprint $t) {
                $t->id('mealSessionId');
                $t->unsignedBigInteger('eventId')->index();
                $t->string('title');
                $t->string('slug')->unique();
                $t->text('description')->nullable();
                $t->date('mealDate');
                $t->time('startTime');
                $t->time('endTime');
                $t->string('location')->nullable();
                $t->string('status')->default('draft'); // draft | active | closed
                $t->unsignedInteger('sortOrder')->default(0);
                $t->unsignedInteger('redeemedCount')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('meal_redemptions')) {
            Schema::create('meal_redemptions', function (Blueprint $t) {
                $t->id('redemptionId');
                $t->unsignedBigInteger('mealSessionId')->index();
                $t->unsignedBigInteger('passId')->index();
                $t->unsignedBigInteger('redeemedBy')->nullable();
                $t->string('deviceName')->nullable();
                $t->dateTime('redeemedAt');
                $t->timestamps();

                // One scan per pass per meal session, enforced by the database
                $t->unique(['mealSessionId', 'passId'], 'meal_redemptions_session_pass_unique');
            });
        } else {
            try {
                Schema::table('meal_redemptions', function (Blueprint $t) {
                    $t->unique(['mealSessionId', 'passId'], 'meal_redemptions_session_pass_unique');
                });
            } catch (\Throwable $e) {
                // Index already exists, OR duplicate rows exist and need cleaning first.
            }
        }

        // Audit trail of denied scans (already redeemed, not accredited, invalid QR...)
        Schema::create('meal_scan_attempts', function (Blueprint $t) {
            $t->id('attemptId');
            $t->unsignedBigInteger('mealSessionId')->nullable()->index();
            $t->unsignedBigInteger('passId')->nullable();
            $t->string('token');
            $t->string('result')->index();
            $t->string('message')->nullable();
            $t->unsignedBigInteger('scannedBy')->nullable();
            $t->string('deviceName')->nullable();
            $t->string('ipAddress', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_scan_attempts');
    }
};
