<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TwitterService
{
    protected $baseUrl = 'https://api.twitter.com/2';
    protected $authUrl = 'https://twitter.com/i/oauth2';

    /**
     * Get OAuth authorization URL (OAuth 2.0 with PKCE)
     */
    public function getAuthorizationUrl($state = null, $codeVerifier = null)
    {
        $clientId = config('socialmedia.twitter.client_id');
        $redirectUri = config('socialmedia.twitter.redirect_uri');
        
        // Generate code verifier and challenge for PKCE
        if (!$codeVerifier) {
            $codeVerifier = bin2hex(random_bytes(32));
        }
        
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        
        $scopes = [
            'tweet.read',
            'tweet.write',
            'users.read',
            'offline.access', // For refresh token
        ];

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state ?? bin2hex(random_bytes(16)),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        // Store code verifier in session for later use
        session(['twitter_code_verifier' => $codeVerifier]);

        return "{$this->authUrl}/authorize?{$params}";
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code, $codeVerifier = null)
    {
        try {
            if (!$codeVerifier) {
                $codeVerifier = session('twitter_code_verifier');
            }

            $response = Http::asForm()
                ->withBasicAuth(
                    config('socialmedia.twitter.client_id'),
                    config('socialmedia.twitter.client_secret')
                )
                ->post("{$this->authUrl}/token", [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('socialmedia.twitter.redirect_uri'),
                    'code_verifier' => $codeVerifier,
                ]);

            if ($response->failed()) {
                throw new Exception('Failed to get access token: ' . $response->body());
            }

            // Clear code verifier from session
            session()->forget('twitter_code_verifier');

            return $response->json();
        } catch (Exception $e) {
            Log::error('Twitter OAuth error: ' . $e->getMessage());
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
            ])->get("{$this->baseUrl}/users/me", [
                'user.fields' => 'id,name,username,profile_image_url',
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get user profile: ' . $response->body());
            }

            return $response->json()['data'] ?? null;
        } catch (Exception $e) {
            Log::error('Twitter get profile error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish tweet
     */
    public function publishPost(SocialAccount $account, ScheduledPost $scheduledPost)
    {
        try {
            // Check if token is expired
            if ($account->is_token_expired) {
                throw new Exception('Access token has expired. Please reconnect your Twitter account.');
            }

            $tweetData = [
                'text' => $scheduledPost->content,
            ];

            // Twitter API v2 supports media, but requires separate upload endpoint
            // For now, text-only tweets
            if (!empty($scheduledPost->media)) {
                // Media upload would go here (requires v1.1 API for upload)
                Log::info('Twitter media upload not yet implemented', [
                    'post_id' => $scheduledPost->id,
                ]);
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/tweets", $tweetData);

            if ($response->failed()) {
                throw new Exception('Failed to publish tweet: ' . $response->body());
            }

            $result = $response->json();
            
            // Mark account as used
            $account->markAsUsed();

            return [
                'success' => true,
                'post_id' => $result['data']['id'] ?? null,
                'data' => $result,
            ];

        } catch (Exception $e) {
            Log::error('Twitter publish error: ' . $e->getMessage(), [
                'account_id' => $account->id,
                'post_id' => $scheduledPost->id,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a tweet
     */
    public function deletePost($tweetId, $accessToken)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->delete("{$this->baseUrl}/tweets/{$tweetId}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Twitter delete tweet error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify account connection
     */
    public function verifyAccount(SocialAccount $account)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
            ])->get("{$this->baseUrl}/users/me");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Twitter verify account error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken($refreshToken)
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('socialmedia.twitter.client_id'),
                    config('socialmedia.twitter.client_secret')
                )
                ->post("{$this->authUrl}/token", [
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            if ($response->failed()) {
                throw new Exception('Failed to refresh token: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Twitter refresh token error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload media to Twitter (uses v1.1 API)
     */
    protected function uploadMedia($accessToken, $mediaUrl)
    {
        try {
            // Download media first
            $mediaContent = file_get_contents($mediaUrl);
            $mediaType = mime_content_type($mediaUrl);

            // Upload to Twitter (chunked upload for large files)
            // This is a simplified version - full implementation would need chunked upload
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->attach('media', $mediaContent, 'media.jpg')
              ->post('https://upload.twitter.com/1.1/media/upload.json');

            if ($response->failed()) {
                throw new Exception('Failed to upload media');
            }

            return $response->json()['media_id_string'] ?? null;

        } catch (Exception $e) {
            Log::error('Twitter media upload error: ' . $e->getMessage());
            return null;
        }
    }
}












