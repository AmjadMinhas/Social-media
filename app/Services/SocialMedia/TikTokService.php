<?php

namespace App\Services\SocialMedia;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TikTokService
{
    protected $baseUrl = 'https://open.tiktokapis.com';
    protected $authUrl = 'https://www.tiktok.com/v2/auth/authorize';

    /**
     * Get authorization URL
     */
    public function getAuthorizationUrl($state)
    {
        $clientKey = config('socialmedia.tiktok.client_key');
        $redirectUri = config('socialmedia.tiktok.redirect_uri');
        
        $scopes = [
            'user.info.basic',
            'video.publish',
            'video.upload',
        ];

        $params = [
            'client_key' => $clientKey,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        return $this->authUrl . '?' . http_build_query($params);
    }

    /**
     * Get access token from authorization code
     */
    public function getAccessToken($code)
    {
        try {
            $response = Http::asForm()->post("{$this->baseUrl}/v2/oauth/token/", [
                'client_key' => config('socialmedia.tiktok.client_key'),
                'client_secret' => config('socialmedia.tiktok.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('socialmedia.tiktok.redirect_uri'),
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get access token: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'access_token' => $data['data']['access_token'] ?? null,
                'refresh_token' => $data['data']['refresh_token'] ?? null,
                'expires_in' => $data['data']['expires_in'] ?? 86400,
                'open_id' => $data['data']['open_id'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('TikTok OAuth error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user profile information
     */
    public function getUserProfile($accessToken)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->post("{$this->baseUrl}/v2/user/info/", [
                'fields' => [
                    'open_id',
                    'union_id',
                    'avatar_url',
                    'display_name',
                    'username',
                ],
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get user profile: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'open_id' => $data['data']['user']['open_id'] ?? null,
                'union_id' => $data['data']['user']['union_id'] ?? null,
                'display_name' => $data['data']['user']['display_name'] ?? null,
                'username' => $data['data']['user']['username'] ?? null,
                'avatar_url' => $data['data']['user']['avatar_url'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('TikTok get profile error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken($refreshToken)
    {
        try {
            $response = Http::asForm()->post("{$this->baseUrl}/v2/oauth/token/", [
                'client_key' => config('socialmedia.tiktok.client_key'),
                'client_secret' => config('socialmedia.tiktok.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to refresh token: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'access_token' => $data['data']['access_token'] ?? null,
                'refresh_token' => $data['data']['refresh_token'] ?? null,
                'expires_in' => $data['data']['expires_in'] ?? 86400,
            ];
        } catch (Exception $e) {
            Log::error('TikTok refresh token error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload video to TikTok
     */
    public function uploadVideo($accessToken, $videoUrl, $caption)
    {
        try {
            // Step 1: Initialize upload
            $initResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/v2/post/publish/video/init/", [
                'post_info' => [
                    'title' => $caption,
                    'privacy_level' => 'SELF_ONLY', // or PUBLIC_TO_EVERYONE
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                    'video_cover_timestamp_ms' => 1000,
                ],
                'source_info' => [
                    'source' => 'FILE_URL',
                    'video_url' => $videoUrl,
                ],
            ]);

            if ($initResponse->failed()) {
                throw new Exception('Failed to initialize upload: ' . $initResponse->body());
            }

            $data = $initResponse->json();
            
            return [
                'publish_id' => $data['data']['publish_id'] ?? null,
                'status' => $data['data']['status'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('TikTok upload video error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check video upload status
     */
    public function checkUploadStatus($accessToken, $publishId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/v2/post/publish/status/fetch/", [
                'publish_id' => $publishId,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to check status: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'status' => $data['data']['status'] ?? null,
                'fail_reason' => $data['data']['fail_reason'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('TikTok check status error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish text post (if supported)
     */
    public function publishPost($accessToken, $content)
    {
        // TikTok primarily supports video content
        // Text-only posts are not supported via API
        throw new Exception('TikTok only supports video posts. Please upload a video.');
    }
}

