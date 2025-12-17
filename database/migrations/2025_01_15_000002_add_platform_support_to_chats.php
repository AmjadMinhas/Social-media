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
        Schema::table('chats', function (Blueprint $table) {
            // Add platform support for unified social inbox
            $table->enum('platform', ['whatsapp', 'facebook', 'instagram', 'twitter', 'tiktok', 'linkedin'])
                  ->default('whatsapp')
                  ->after('type');
            
            // Store platform-specific message ID (e.g., Facebook post ID, Instagram comment ID)
            $table->string('platform_message_id')->nullable()->after('wam_id');
            
            // Store platform-specific thread/conversation ID
            $table->string('platform_thread_id')->nullable()->after('platform_message_id');
            
            // Store additional platform-specific data
            $table->json('platform_data')->nullable()->after('metadata');
            
            // Add index for platform queries
            $table->index(['organization_id', 'platform']);
            $table->index(['contact_id', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'platform']);
            $table->dropIndex(['contact_id', 'platform']);
            $table->dropColumn(['platform', 'platform_message_id', 'platform_thread_id', 'platform_data']);
        });
    }
};









