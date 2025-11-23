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
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('content');
            $table->json('platforms'); // ['facebook', 'instagram', 'tiktok', 'twitter', 'linkedin']
            $table->json('media')->nullable(); // Array of media URLs/paths
            $table->dateTime('scheduled_at');
            $table->dateTime('published_at')->nullable();
            $table->enum('status', ['scheduled', 'publishing', 'published', 'failed', 'cancelled'])->default('scheduled');
            $table->text('error_message')->nullable();
            $table->json('platform_post_ids')->nullable(); // Store post IDs from each platform
            $table->timestamps();
            $table->softDeletes('deleted_at');
            
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['organization_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
    }
};
