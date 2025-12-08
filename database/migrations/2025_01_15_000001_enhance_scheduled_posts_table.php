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
        Schema::table('scheduled_posts', function (Blueprint $table) {
            // Add publish type: 'now', 'scheduled', 'time_range'
            $table->enum('publish_type', ['now', 'scheduled', 'time_range'])->default('scheduled')->after('scheduled_at');
            
            // Add time range fields for scheduling within a range
            $table->dateTime('scheduled_from')->nullable()->after('publish_type');
            $table->dateTime('scheduled_to')->nullable()->after('scheduled_from');
            
            // Add index for time range queries
            $table->index(['publish_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->dropIndex(['publish_type', 'status']);
            $table->dropColumn(['publish_type', 'scheduled_from', 'scheduled_to']);
        });
    }
};

