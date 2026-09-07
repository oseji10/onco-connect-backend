<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::table('abstracts', function (Blueprint $table) {
        //     $table->integer('overall_rank')->nullable()->after('average_score');
        //     $table->integer('sub_theme_rank')->nullable()->after('overall_rank');
        //     $table->timestamp('notification_sent_at')->nullable()->after('submitted_at');
        // });
    }

    public function down(): void
    {
        // Schema::table('abstracts', function (Blueprint $table) {
        //     $table->dropColumn([
        //         'overall_rank',
        //         'sub_theme_rank',
        //         'notification_sent_at'
        //     ]);
        // });
    }
};