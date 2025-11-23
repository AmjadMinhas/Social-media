<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SocialAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'user_id',
        'platform',
        'platform_user_id',
        'platform_username',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'platform_data',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'platform_data' => 'array',
        'token_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $appends = ['platform_name', 'is_token_expired'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledPosts()
    {
        return $this->hasMany(ScheduledPost::class);
    }

    /**
     * Get formatted platform name
     */
    public function getPlatformNameAttribute()
    {
        $names = [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'twitter' => 'X (Twitter)',
            'linkedin' => 'LinkedIn'
        ];
        
        return $names[$this->platform] ?? $this->platform;
    }

    /**
     * Check if token is expired
     */
    public function getIsTokenExpiredAttribute()
    {
        if (!$this->token_expires_at) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->token_expires_at);
    }

    /**
     * Check if token is about to expire (within 7 days)
     */
    public function isTokenExpiringSoon()
    {
        if (!$this->token_expires_at) {
            return false;
        }
        
        return Carbon::now()->addDays(7)->isAfter($this->token_expires_at);
    }

    /**
     * Mark account as used
     */
    public function markAsUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope to get active accounts only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('token_expires_at')
                  ->orWhere('token_expires_at', '>', now());
            });
    }

    /**
     * Scope to get accounts by platform
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }
}

