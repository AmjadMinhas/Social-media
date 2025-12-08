<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'user_id',
        'title',
        'content',
        'platforms',
        'media',
        'scheduled_at',
        'publish_type',
        'scheduled_from',
        'scheduled_to',
        'published_at',
        'status',
        'error_message',
        'platform_post_ids',
        'deleted_at'
    ];

    protected $casts = [
        'platforms' => 'array',
        'media' => 'array',
        'platform_post_ids' => 'array',
        'scheduled_at' => 'datetime',
        'scheduled_from' => 'datetime',
        'scheduled_to' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected $appends = ['platforms_list', 'status_label'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted platforms list
     */
    public function getPlatformsListAttribute()
    {
        if (!$this->platforms) {
            return '';
        }
        
        $platformNames = [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'twitter' => 'X (Twitter)',
            'linkedin' => 'LinkedIn'
        ];
        
        return collect($this->platforms)
            ->map(fn($platform) => $platformNames[$platform] ?? $platform)
            ->join(', ');
    }

    /**
     * Get formatted status label
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'scheduled' => 'Scheduled',
            'publishing' => 'Publishing',
            'published' => 'Published',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => $this->status
        };
    }

    /**
     * Check if all required social accounts are connected
     */
    public function hasRequiredAccounts()
    {
        if (!$this->platforms || empty($this->platforms)) {
            return false;
        }

        foreach ($this->platforms as $platform) {
            $account = SocialAccount::where('organization_id', $this->organization_id)
                ->where('platform', $platform)
                ->active()
                ->first();
            
            if (!$account) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing social accounts
     */
    public function getMissingAccounts()
    {
        $missing = [];

        if (!$this->platforms) {
            return $missing;
        }

        foreach ($this->platforms as $platform) {
            $account = SocialAccount::where('organization_id', $this->organization_id)
                ->where('platform', $platform)
                ->active()
                ->first();
            
            if (!$account) {
                $missing[] = $platform;
            }
        }

        return $missing;
    }

    /**
     * Scope to get posts that are ready to be published
     */
    public function scopeReadyToPublish($query)
    {
        return $query->where('status', 'scheduled')
            ->where(function ($q) {
                $q->where('publish_type', 'now')
                  ->orWhere(function ($subQ) {
                      $subQ->where('publish_type', 'scheduled')
                           ->where('scheduled_at', '<=', now());
                  })
                  ->orWhere(function ($subQ) {
                      $subQ->where('publish_type', 'time_range')
                           ->where('scheduled_from', '<=', now())
                           ->where('scheduled_to', '>=', now());
                  });
            });
    }

    /**
     * Calculate random scheduled time within time range
     */
    public function calculateRandomScheduledTime()
    {
        if ($this->publish_type !== 'time_range' || !$this->scheduled_from || !$this->scheduled_to) {
            return $this->scheduled_at;
        }

        $from = $this->scheduled_from->timestamp;
        $to = $this->scheduled_to->timestamp;
        
        // Generate random timestamp within range
        $randomTimestamp = rand($from, $to);
        
        return \Carbon\Carbon::createFromTimestamp($randomTimestamp);
    }
}



