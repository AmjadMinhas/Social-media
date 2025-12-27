<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TikTokService
{
    protected $baseUrl = 'https://open.tiktokapis.com';
    protected $authUrl = 'https://www.tiktok.com/v2/auth/authorize';

    /**
     * Generate PKCE code verifier and challenge
     */
    protected function generatePKCE()
    {
        // Generate a random code verifier (43-128 characters)
        $codeVerifier = bin2hex(random_bytes(32));
        
        // Generate code challenge (SHA256 hash, base64url encoded)
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        
        return [
            'code_verifier' => $codeVerifier,
            'code_challenge' => $codeChallenge,
        ];
    }

    /**
     * Get authorization URL with PKCE
     */
    public function getAuthorizationUrl($state, $codeVerifier = null, $codeChallenge = null)
    {
        $clientKey = config('socialmedia.tiktok.client_key');
        $redirectUri = config('socialmedia.tiktok.redirect_uri');
        
        // Validate client_key is set
        if (empty($clientKey)) {
            Log::error('TikTok OAuth: client_key is not set in configuration');
            throw new Exception('TikTok client_key is not configured. Please set TIKTOK_CLIENT_KEY in your .env file.');
        }
        
        // Validate redirect_uri is set
        if (empty($redirectUri)) {
            Log::error('TikTok OAuth: redirect_uri is not set in configuration');
            throw new Exception('TikTok redirect_uri is not configured. Please set APP_URL in your .env file.');
        }
        
        // Generate PKCE if not provided
        if (!$codeVerifier || !$codeChallenge) {
            $pkce = $this->generatePKCE();
            $codeVerifier = $pkce['code_verifier'];
            $codeChallenge = $pkce['code_challenge'];
        }
        
        // Store code_verifier in session for later use
        session(['tiktok_code_verifier' => $codeVerifier]);
        
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
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        
        Log::info('TikTok OAuth: Generating authorization URL', [
            'has_client_key' => !empty($clientKey),
            'client_key_length' => strlen($clientKey ?? ''),
            'redirect_uri' => $redirectUri,
            'auth_url' => $this->authUrl,
        ]);

        return $this->authUrl . '?' . http_build_query($params);
    }

    /**
     * Get access token from authorization code (with PKCE)
     */
    public function getAccessToken($code, $codeVerifier = null)
    {
        try {
            // Get code_verifier from session if not provided
            if (!$codeVerifier) {
                $codeVerifier = session('tiktok_code_verifier');
            }
            
            if (!$codeVerifier) {
                throw new Exception('Code verifier is required for PKCE flow');
            }
            
            $response = Http::asForm()->post("{$this->baseUrl}/v2/oauth/token/", [
                'client_key' => config('socialmedia.tiktok.client_key'),
                'client_secret' => config('socialmedia.tiktok.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('socialmedia.tiktok.redirect_uri'),
                'code_verifier' => $codeVerifier, // PKCE: include code_verifier
            ]);

            if ($response->failed()) {
                Log::error('TikTok token error response: ' . $response->body());
                throw new Exception('Failed to get access token: ' . $response->body());
            }

            $data = $response->json();
            
            // Clear code_verifier from session after successful token exchange
            session()->forget('tiktok_code_verifier');
            
            return [
                'access_token' => $data['data']['access_token'] ?? null,
                'refresh_token' => $data['data']['refresh_token'] ?? null,
                'expires_in' => $data['data']['expires_in'] ?? 86400,
                'open_id' => $data['data']['open_id'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('TikTok OAuth error: ' . $e->getMessage());
            // Clear code_verifier on error too
            session()->forget('tiktok_code_verifier');
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
     * Publish post to TikTok
     */
    public function publishPost(\App\Models\SocialAccount $account, \App\Models\ScheduledPost $scheduledPost)
    {
        try {
            // Check if token is expired
            if ($account->is_token_expired) {
                throw new Exception('Access token has expired. Please reconnect your TikTok account.');
            }

            $accessToken = $account->access_token;

            // TikTok requires video content
            if (empty($scheduledPost->media) || !is_array($scheduledPost->media) || count($scheduledPost->media) === 0) {
                throw new Exception('TikTok requires video content. Please upload a video.');
            }

            $videoUrl = $scheduledPost->media[0];
            $caption = $scheduledPost->content;

            // Step 1: Initialize video upload
            $initResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/v2/post/publish/video/init/", [
                'post_info' => [
                    'title' => $caption ?: 'Video Post',
                    'privacy_level' => 'PUBLIC_TO_EVERYONE', // or 'SELF_ONLY' for private
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
                throw new Exception('Failed to initialize TikTok upload: ' . $initResponse->body());
            }

            $initData = $initResponse->json();
            $publishId = $initData['data']['publish_id'] ?? null;

            if (!$publishId) {
                throw new Exception('No publish ID returned from TikTok');
            }

            // Step 2: Poll for upload status (TikTok uploads are asynchronous)
            // Note: For production, consider using a job queue for status polling
            $maxAttempts = 30; // 30 attempts with 2 second delay = 60 seconds max
            $attempt = 0;
            $status = null;

            while ($attempt < $maxAttempts) {
                usleep(2000000); // Wait 2 seconds between checks (2,000,000 microseconds)
                $attempt++;

                $statusResponse = $this->checkUploadStatus($accessToken, $publishId);
                $status = $statusResponse['status'] ?? null;

                if ($status === 'PUBLISHED') {
                    // Mark account as used
                    $account->markAsUsed();

                    return [
                        'success' => true,
                        'post_id' => $publishId,
                        'data' => [
                            'publish_id' => $publishId,
                            'status' => $status,
                        ],
                    ];
                } elseif ($status === 'FAILED') {
                    $failReason = $statusResponse['fail_reason'] ?? 'Unknown error';
                    throw new Exception('TikTok upload failed: ' . $failReason);
                }

                // Continue polling if status is PROCESSING or other intermediate states
            }

            // If we get here, the upload is still processing
            // Return success but note that it's still processing
            return [
                'success' => true,
                'post_id' => $publishId,
                'data' => [
                    'publish_id' => $publishId,
                    'status' => $status ?? 'PROCESSING',
                    'message' => 'Video is still being processed. Please check TikTok app for final status.',
                ],
            ];

        } catch (Exception $e) {
            Log::error('TikTok publish error: ' . $e->getMessage(), [
                'account_id' => $account->id,
                'post_id' => $scheduledPost->id,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}



