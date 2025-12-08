<?php

namespace App\Console\Commands;

use App\Jobs\PublishScheduledPostJob;
use App\Models\ScheduledPost;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled posts that are ready to be published';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled posts...');

        // Get posts that are scheduled and ready to be published
        $posts = ScheduledPost::readyToPublish()->get();

        if ($posts->isEmpty()) {
            $this->info('No posts ready to be published.');
            return 0;
        }

        $this->info("Found {$posts->count()} post(s) ready to be published.");

        foreach ($posts as $post) {
            try {
                // For time_range posts, calculate random scheduled time
                if ($post->publish_type === 'time_range' && !$post->scheduled_at) {
                    $randomTime = $post->calculateRandomScheduledTime();
                    $post->update(['scheduled_at' => $randomTime]);
                    $this->info("Calculated random time for post {$post->id}: {$randomTime}");
                }

                $this->info("Dispatching job for post ID: {$post->id} (UUID: {$post->uuid})");
                
                // Dispatch job to publish the post
                PublishScheduledPostJob::dispatch($post);
                
                $this->info("Job dispatched successfully for post: {$post->title}");
                
                Log::info('Scheduled post job dispatched', [
                    'post_id' => $post->id,
                    'uuid' => $post->uuid,
                    'publish_type' => $post->publish_type,
                    'scheduled_at' => $post->scheduled_at,
                ]);

            } catch (\Exception $e) {
                $this->error("Failed to dispatch job for post ID: {$post->id}");
                $this->error($e->getMessage());
                
                Log::error('Failed to dispatch scheduled post job', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Processing complete.');
        return 0;
    }
}




