<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * WhatsApp Request Queue Service
 * 
 * This service manages concurrent WhatsApp API requests to allow
 * both business app and API usage on the same phone number simultaneously.
 * It implements rate limiting and request queuing to prevent conflicts.
 */
class WhatsAppRequestQueue
{
    protected $organizationId;
    protected $phoneNumberId;
    protected $rateLimitPerMinute = 20; // WhatsApp API rate limit
    protected $rateLimitPerHour = 1000;

    public function __construct($organizationId, $phoneNumberId)
    {
        $this->organizationId = $organizationId;
        $this->phoneNumberId = $phoneNumberId;
    }

    /**
     * Queue a WhatsApp API request
     * 
     * @param callable $requestCallback The actual request to execute
     * @param string $requestType Type of request (message, template, media, etc.)
     * @return mixed
     */
    public function queueRequest(callable $requestCallback, $requestType = 'message')
    {
        $cacheKey = $this->getRateLimitKey($requestType);
        
        // Check rate limit
        if (!$this->checkRateLimit($cacheKey)) {
            Log::warning('WhatsApp rate limit exceeded', [
                'organization_id' => $this->organizationId,
                'phone_number_id' => $this->phoneNumberId,
                'request_type' => $requestType,
            ]);
            
            // Wait a bit and retry
            sleep(3);
            
            // Retry once
            if (!$this->checkRateLimit($cacheKey)) {
                throw new \Exception('WhatsApp API rate limit exceeded. Please try again later.');
            }
        }

        // Increment rate limit counter
        $this->incrementRateLimit($cacheKey);

        try {
            // Execute the request
            $result = $requestCallback();
            
            return $result;
        } catch (\Exception $e) {
            Log::error('WhatsApp API request failed', [
                'organization_id' => $this->organizationId,
                'phone_number_id' => $this->phoneNumberId,
                'request_type' => $requestType,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Check if request is within rate limit
     */
    protected function checkRateLimit($cacheKey)
    {
        $minuteKey = "{$cacheKey}:minute:" . now()->format('Y-m-d-H-i');
        $hourKey = "{$cacheKey}:hour:" . now()->format('Y-m-d-H');
        
        $minuteCount = Cache::get($minuteKey, 0);
        $hourCount = Cache::get($hourKey, 0);
        
        return $minuteCount < $this->rateLimitPerMinute && $hourCount < $this->rateLimitPerHour;
    }

    /**
     * Increment rate limit counter
     */
    protected function incrementRateLimit($cacheKey)
    {
        $minuteKey = "{$cacheKey}:minute:" . now()->format('Y-m-d-H-i');
        $hourKey = "{$cacheKey}:hour:" . now()->format('Y-m-d-H');
        
        Cache::increment($minuteKey);
        Cache::put($minuteKey, Cache::get($minuteKey, 0), now()->addMinutes(2));
        
        Cache::increment($hourKey);
        Cache::put($hourKey, Cache::get($hourKey, 0), now()->addHours(2));
    }

    /**
     * Get rate limit cache key
     */
    protected function getRateLimitKey($requestType)
    {
        return "whatsapp:ratelimit:{$this->organizationId}:{$this->phoneNumberId}:{$requestType}";
    }

    /**
     * Get current rate limit status
     */
    public function getRateLimitStatus($requestType = 'message')
    {
        $cacheKey = $this->getRateLimitKey($requestType);
        $minuteKey = "{$cacheKey}:minute:" . now()->format('Y-m-d-H-i');
        $hourKey = "{$cacheKey}:hour:" . now()->format('Y-m-d-H');
        
        return [
            'minute_count' => Cache::get($minuteKey, 0),
            'minute_limit' => $this->rateLimitPerMinute,
            'hour_count' => Cache::get($hourKey, 0),
            'hour_limit' => $this->rateLimitPerHour,
            'minute_remaining' => max(0, $this->rateLimitPerMinute - Cache::get($minuteKey, 0)),
            'hour_remaining' => max(0, $this->rateLimitPerHour - Cache::get($hourKey, 0)),
        ];
    }

    /**
     * Reset rate limit counters (for testing/admin purposes)
     */
    public function resetRateLimit($requestType = 'message')
    {
        $cacheKey = $this->getRateLimitKey($requestType);
        $minuteKey = "{$cacheKey}:minute:" . now()->format('Y-m-d-H-i');
        $hourKey = "{$cacheKey}:hour:" . now()->format('Y-m-d-H');
        
        Cache::forget($minuteKey);
        Cache::forget($hourKey);
    }
}




