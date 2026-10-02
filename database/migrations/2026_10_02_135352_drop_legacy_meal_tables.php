<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * OPTIONAL. Back up the database first and run only once you are sure you no
 * longer need per-meal tickets, food supplies, distributions or ratings.
 * scan_logs is intentionally NOT dropped (it may hold historic session scans).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['food_distributions', 'food_supplies', 'meal_ratings', 'meal_tickets', 'meals'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible by design.
    }
};