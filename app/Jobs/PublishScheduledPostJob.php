<?php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\SocialMedia\FacebookService;
use App\Services\SocialMedia\LinkedInService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected $scheduledPost;

    /**
     * Create a new job instance.
     */
    public function __construct(ScheduledPost $scheduledPost)
    {
        $this->scheduledPost = $scheduledPost;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $post = $this->scheduledPost;

            // Check if post is still scheduled
            if ($post->status !== 'scheduled') {
                Log::info("Post {$post->id} is not scheduled, skipping", ['status' => $post->status]);
                return;
            }

            // Update status to publishing
            $post->update(['status' => 'publishing']);

            $results = [];
            $errors = [];
            $platformPostIds = [];

            // Get organization's social accounts
            $organizationId = $post->organization_id;

            foreach ($post->platforms as $platform) {
                try {
                    // Get active social account for this platform
                    $account = SocialAccount::where('organization_id', $organizationId)
                        ->where('platform', $platform)
                        ->active()
                        ->first();

                    if (!$account) {
                        $errors[$platform] = "No active {$platform} account found";
                        Log::warning("No active account found for platform: {$platform}", [
                            'organization_id' => $organizationId,
                            'post_id' => $post->id,
                        ]);
                        continue;
                    }

                    // Publish to platform
                    $result = $this->publishToPlatform($platform, $account, $post);

                    if ($result['success']) {
                        $results[$platform] = 'success';
                        if (isset($result['post_id'])) {
                            $platformPostIds[$platform] = $result['post_id'];
                        }
                        Log::info("Successfully published to {$platform}", [
                            'post_id' => $post->id,
                            'platform_post_id' => $result['post_id'] ?? null,
                        ]);
                    } else {
                        $errors[$platform] = $result['error'] ?? 'Unknown error';
                        Log::error("Failed to publish to {$platform}", [
                            'post_id' => $post->id,
                            'error' => $result['error'] ?? 'Unknown error',
                        ]);
                    }

                } catch (\Exception $e) {
                    $errors[$platform] = $e->getMessage();
                    Log::error("Exception publishing to {$platform}", [
                        'post_id' => $post->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Update post status based on results
            if (empty($errors)) {
                // All platforms succeeded
                $post->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'platform_post_ids' => $platformPostIds,
                    'error_message' => null,
                ]);
                Log::info("Post {$post->id} published successfully to all platforms");
            } else if (count($errors) === count($post->platforms)) {
                // All platforms failed
                $post->update([
                    'status' => 'failed',
                    'error_message' => json_encode($errors),
                ]);
                Log::error("Post {$post->id} failed to publish to all platforms", ['errors' => $errors]);
            } else {
                // Partial success
                $post->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'platform_post_ids' => $platformPostIds,
                    'error_message' => json_encode(['partial_errors' => $errors]),
                ]);
                Log::warning("Post {$post->id} partially published", [
                    'successes' => $results,
                    'errors' => $errors
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Critical error in PublishScheduledPostJob', [
                'post_id' => $this->scheduledPost->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->scheduledPost->update([
                'status' => 'failed',
                'error_message' => 'Critical error: ' . $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Publish to specific platform
     */
    protected function publishToPlatform($platform, SocialAccount $account, ScheduledPost $post)
    {
        switch ($platform) {
            case 'facebook':
                $service = app(\App\Services\SocialMedia\FacebookService::class);
                return $service->publishPost($account, $post);

            case 'linkedin':
                $service = app(\App\Services\SocialMedia\LinkedInService::class);
                return $service->publishPost($account, $post);

            case 'instagram':
                $service = app(\App\Services\SocialMedia\InstagramService::class);
                return $service->publishPost($account, $post);

            case 'twitter':
                $service = app(\App\Services\SocialMedia\TwitterService::class);
                return $service->publishPost($account, $post);

            case 'tiktok':
                $service = app(\App\Services\SocialMedia\TikTokService::class);
                return $service->publishPost($account, $post);

            default:
                return ['success' => false, 'error' => "Unknown platform: {$platform}"];
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('PublishScheduledPostJob failed permanently', [
            'post_id' => $this->scheduledPost->id,
            'error' => $exception->getMessage(),
        ]);

        $this->scheduledPost->update([
            'status' => 'failed',
            'error_message' => 'Job failed after maximum retries: ' . $exception->getMessage(),
        ]);
    }
}

