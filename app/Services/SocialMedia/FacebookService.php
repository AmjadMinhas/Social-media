<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FacebookService
{
    protected $apiVersion;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiVersion = config('socialmedia.facebook.graph_api_version');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl($state = null)
    {
        $appId = config('socialmedia.facebook.app_id');
        $redirectUri = config('socialmedia.facebook.redirect_uri');
        $permissions = implode(',', config('socialmedia.facebook.permissions'));

        $params = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => $permissions,
            'state' => $state ?? bin2hex(random_bytes(16)),
            'response_type' => 'code',
        ]);

        return "https://www.facebook.com/{$this->apiVersion}/dialog/oauth?{$params}";
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code)
    {
        try {
            $response = Http::get("{$this->baseUrl}/oauth/access_token", [
                'client_id' => config('socialmedia.facebook.app_id'),
                'client_secret' => config('socialmedia.facebook.app_secret'),
                'redirect_uri' => config('socialmedia.facebook.redirect_uri'),
                'code' => $code,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get access token: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Facebook OAuth error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get long-lived access token
     */
    public function getLongLivedToken($shortLivedToken)
    {
        try {
            $response = Http::get("{$this->baseUrl}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('socialmedia.facebook.app_id'),
                'client_secret' => config('socialmedia.facebook.app_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get long-lived token: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Facebook long-lived token error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's Facebook pages
     */
    public function getUserPages($accessToken)
    {
        try {
            $response = Http::get("{$this->baseUrl}/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token,picture',
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get user pages: ' . $response->body());
            }

            return $response->json()['data'] ?? [];
        } catch (Exception $e) {
            Log::error('Facebook get pages error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get page long-lived token
     */
    public function getPageLongLivedToken($pageId, $pageAccessToken)
    {
        try {
            $response = Http::get("{$this->baseUrl}/{$pageId}", [
                'fields' => 'access_token',
                'access_token' => $pageAccessToken,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get page long-lived token: ' . $response->body());
            }

            return $response->json()['access_token'] ?? null;
        } catch (Exception $e) {
            Log::error('Facebook page token error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Post to Facebook Page
     */
    public function publishPost(SocialAccount $account, ScheduledPost $scheduledPost)
    {
        try {
            // Check if token is expired
            if ($account->is_token_expired) {
                throw new Exception('Access token has expired. Please reconnect your Facebook account.');
            }

            $pageId = $account->platform_data['page_id'] ?? $account->platform_user_id;
            
            $postData = [
                'message' => $scheduledPost->content,
                'access_token' => $account->access_token,
            ];

            // Add media if present
            if (!empty($scheduledPost->media)) {
                // For single image
                if (count($scheduledPost->media) === 1) {
                    $postData['url'] = $scheduledPost->media[0];
                    $endpoint = "{$this->baseUrl}/{$pageId}/photos";
                } 
                // For multiple images (carousel)
                else if (count($scheduledPost->media) > 1) {
                    // TODO: Implement multi-photo upload
                    $endpoint = "{$this->baseUrl}/{$pageId}/feed";
                }
            } else {
                // Text only post
                $endpoint = "{$this->baseUrl}/{$pageId}/feed";
            }

            $response = Http::post($endpoint, $postData);

            if ($response->failed()) {
                throw new Exception('Failed to publish post: ' . $response->body());
            }

            $result = $response->json();
            
            // Mark account as used
            $account->markAsUsed();

            return [
                'success' => true,
                'post_id' => $result['id'] ?? $result['post_id'] ?? null,
                'data' => $result,
            ];

        } catch (Exception $e) {
            Log::error('Facebook publish error: ' . $e->getMessage(), [
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
     * Delete a post from Facebook
     */
    public function deletePost($postId, $accessToken)
    {
        try {
            $response = Http::delete("{$this->baseUrl}/{$postId}", [
                'access_token' => $accessToken,
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Facebook delete post error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify account connection
     */
    public function verifyAccount(SocialAccount $account)
    {
        try {
            $pageId = $account->platform_data['page_id'] ?? $account->platform_user_id;
            
            $response = Http::get("{$this->baseUrl}/{$pageId}", [
                'fields' => 'id,name',
                'access_token' => $account->access_token,
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Facebook verify account error: ' . $e->getMessage());
            return false;
        }
    }
}

