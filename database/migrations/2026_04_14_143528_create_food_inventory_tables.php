

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
    /**
     * Run the migrations.
     */
    // public function up(): void
    // {
    //     // Food Supplies Table (from vendors)
    //     Schema::create('food_supplies', function (Blueprint $table) {
    //         $table->id('supplyId');
    //         $table->unsignedBigInteger('eventId');
    //         $table->unsignedBigInteger('mealSessionId');
    //         $table->string('foodItem'); // e.g., "Jollof Rice", "Fried Rice & Chicken"
    //         $table->string('vendorName');
    //         $table->integer('quantitySupplied'); // Total received from vendor
    //         $table->integer('quantityDistributed')->default(0);
    //         $table->integer('quantityRemaining'); // quantitySupplied - quantityDistributed
    //         $table->date('supplyDate');
    //         $table->text('notes')->nullable();
    //         $table->unsignedBigInteger('recordedBy')->nullable(); // User who recorded the supply
    //         $table->timestamps();

    //         $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
    //         $table->foreign('mealSessionId')->references('mealSessionId')->on('meal_sessions')->onDelete('cascade');
    //         $table->foreign('recordedBy')->references('id')->on('users')->onDelete('set null');

    //         $table->index(['eventId', 'mealSessionId']);
    //         $table->index(['mealSessionId', 'quantityRemaining']);
    //         $table->index('supplyDate');
    //     });

    //     // Food Distributions Table (tracking each meal given out)
    //     Schema::create('food_distributions', function (Blueprint $table) {
    //         $table->id('distributionId');
    //         $table->unsignedBigInteger('eventId');
    //         $table->unsignedBigInteger('mealSessionId');
    //         $table->unsignedBigInteger('attendeeId');
    //         $table->unsignedBigInteger('foodSupplyId');
    //         $table->string('ticketId')->nullable(); // Reference to meal ticket if applicable
    //         $table->unsignedBigInteger('distributedBy')->nullable(); // Scanner user
    //         $table->string('deviceName')->nullable(); // Scanner device name
    //         $table->timestamps();

    //         $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
    //         $table->foreign('mealSessionId')->references('mealSessionId')->on('meal_sessions')->onDelete('cascade');
    //         $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->onDelete('cascade');
    //         $table->foreign('foodSupplyId')->references('supplyId')->on('food_supplies')->onDelete('cascade');
    //         $table->foreign('distributedBy')->references('id')->on('users')->onDelete('set null');

    //         $table->index(['eventId', 'created_at']);
    //         $table->index(['attendeeId', 'mealSessionId', 'created_at']);
    //         $table->index('mealSessionId');
    //         $table->index('foodSupplyId');

    //         // Prevent duplicate distributions - one food per attendee per session per day
    //         $table->unique(['attendeeId', 'mealSessionId', 'created_at'], 'unique_daily_distribution');
    //     });

    //     // Meal Ratings Table (participant feedback)
    //     Schema::create('meal_ratings', function (Blueprint $table) {
    //         $table->id('ratingId');
    //         $table->unsignedBigInteger('eventId');
    //         $table->unsignedBigInteger('mealSessionId');
    //         $table->unsignedBigInteger('attendeeId');
    //         $table->integer('rating'); // 1-5 stars
    //         $table->text('comment')->nullable();
    //         $table->timestamps();

    //         $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
    //         $table->foreign('mealSessionId')->references('mealSessionId')->on('meal_sessions')->onDelete('cascade');
    //         $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->onDelete('cascade');

    //         $table->index(['eventId', 'created_at']);
    //         $table->index(['mealSessionId', 'rating']);
    //         $table->index('attendeeId');
            
    //         // One rating per attendee per meal session
    //         $table->unique(['attendeeId', 'mealSessionId'], 'unique_session_rating');
    //     });
    // }

    /**
     * Reverse the migrations.
     */
    // public function down(): void
    // {
    //     Schema::dropIfExists('meal_ratings');
    //     Schema::dropIfExists('food_distributions');
    //     Schema::dropIfExists('food_supplies');
    // }
// };


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
        // Food Supplies Table (from vendors)
        Schema::create('food_supplies', function (Blueprint $table) {
            $table->id('supplyId');
            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('mealSessionId');
            $table->string('foodItem'); // e.g., "Jollof Rice", "Fried Rice & Chicken"
            $table->string('vendorName');
            $table->integer('quantitySupplied'); // Total received from vendor
            $table->integer('quantityDistributed')->default(0);
            $table->integer('quantityRemaining'); // quantitySupplied - quantityDistributed
            $table->date('supplyDate');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recordedBy')->nullable(); // User who recorded the supply
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
            $table->foreign('mealSessionId')->references('mealSessionId')->on('meal_sessions')->onDelete('cascade');
            $table->foreign('recordedBy')->references('id')->on('users')->onDelete('set null');

            $table->index(['eventId', 'mealSessionId']);
            $table->index(['mealSessionId', 'quantityRemaining']);
            $table->index('supplyDate');
        });

        // Food Distributions Table (tracking each meal given out)
        Schema::create('food_distributions', function (Blueprint $table) {
            $table->id('distributionId');
            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('mealSessionId');
            $table->unsignedBigInteger('attendeeId');
            $table->unsignedBigInteger('foodSupplyId');
            $table->string('ticketId')->nullable(); // Reference to meal ticket if applicable
            $table->unsignedBigInteger('distributedBy')->nullable(); // Scanner user
            $table->string('deviceName')->nullable(); // Scanner device name
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
            $table->foreign('mealSessionId')->references('mealSessionId')->on('meal_sessions')->onDelete('cascade');
            $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->onDelete('cascade');
            $table->foreign('foodSupplyId')->references('supplyId')->on('food_supplies')->onDelete('cascade');
            $table->foreign('distributedBy')->references('id')->on('users')->onDelete('set null');

            $table->index(['eventId', 'created_at']);
            $table->index(['attendeeId', 'mealSessionId', 'created_at']);
            $table->index('mealSessionId');
            $table->index('foodSupplyId');

            // Prevent duplicate distributions - one food per attendee per session per day
            $table->unique(['attendeeId', 'mealSessionId', 'created_at'], 'unique_daily_distribution');
        });

        // Meal Ratings Table (participant feedback - anonymous but unique)
        Schema::create('meal_ratings', function (Blueprint $table) {
            $table->id('ratingId');
            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('mealSessionId');
            $table->unsignedBigInteger('attendeeId')->nullable(); // Nullable for anonymous ratings
            $table->integer('rating'); // 1-5 stars
            $table->text('comment')->nullable();
            
            // Anonymous tracking fields
            $table->string('deviceIdentifier')->nullable(); // Hash of device fingerprint + IP + User Agent
            $table->string('ipAddress')->nullable(); // For analytics
            $table->text('userAgent')->nullable(); // For analytics
            
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
            $table->foreign('mealSessionId')->references('mealSessionId')->on('meal_sessions')->onDelete('cascade');
            $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->onDelete('cascade');

            $table->index(['eventId', 'created_at']);
            $table->index(['mealSessionId', 'rating']);
            $table->index('attendeeId');
            $table->index('deviceIdentifier');
            
            // One rating per device per meal session (for anonymous ratings)
            $table->unique(['mealSessionId', 'deviceIdentifier'], 'unique_device_session_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_ratings');
        Schema::dropIfExists('food_distributions');
        Schema::dropIfExists('food_supplies');
    }
};