<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class InstagramService
{
    protected $apiVersion;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiVersion = config('socialmedia.instagram.graph_api_version');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Get OAuth authorization URL
     * Note: Instagram uses Facebook OAuth with instagram_basic and instagram_content_publish scopes
     */
    public function getAuthorizationUrl($state = null)
    {
        $appId = config('socialmedia.instagram.app_id');
        $redirectUri = config('socialmedia.instagram.redirect_uri');
        
        $scopes = [
            'instagram_basic',
            'instagram_content_publish',
            'pages_show_list',
            'pages_read_engagement',
        ];

        $params = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
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
                'client_id' => config('socialmedia.instagram.app_id'),
                'client_secret' => config('socialmedia.instagram.app_secret'),
                'redirect_uri' => config('socialmedia.instagram.redirect_uri'),
                'code' => $code,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get access token: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Instagram OAuth error: ' . $e->getMessage());
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
                'client_id' => config('socialmedia.instagram.app_id'),
                'client_secret' => config('socialmedia.instagram.app_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get long-lived token: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Instagram long-lived token error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's Instagram Business Accounts
     */
    public function getInstagramAccounts($accessToken)
    {
        try {
            // First get Facebook pages
            $pagesResponse = Http::get("{$this->baseUrl}/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token',
            ]);

            if ($pagesResponse->failed()) {
                throw new Exception('Failed to get pages: ' . $pagesResponse->body());
            }

            $pages = $pagesResponse->json()['data'] ?? [];
            $instagramAccounts = [];

            // For each page, get connected Instagram account
            foreach ($pages as $page) {
                $igResponse = Http::get("{$this->baseUrl}/{$page['id']}", [
                    'fields' => 'instagram_business_account',
                    'access_token' => $page['access_token'],
                ]);

                if ($igResponse->successful()) {
                    $igData = $igResponse->json();
                    
                    if (isset($igData['instagram_business_account'])) {
                        $igAccountId = $igData['instagram_business_account']['id'];
                        
                        // Get Instagram account details
                        $detailsResponse = Http::get("{$this->baseUrl}/{$igAccountId}", [
                            'fields' => 'id,username,profile_picture_url',
                            'access_token' => $page['access_token'],
                        ]);

                        if ($detailsResponse->successful()) {
                            $details = $detailsResponse->json();
                            $instagramAccounts[] = [
                                'id' => $igAccountId,
                                'username' => $details['username'] ?? 'Unknown',
                                'profile_picture' => $details['profile_picture_url'] ?? null,
                                'page_id' => $page['id'],
                                'page_name' => $page['name'],
                                'access_token' => $page['access_token'],
                            ];
                        }
                    }
                }
            }

            return $instagramAccounts;
        } catch (Exception $e) {
            Log::error('Instagram get accounts error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish post to Instagram
     */
    public function publishPost(SocialAccount $account, ScheduledPost $scheduledPost)
    {
        try {
            // Check if token is expired
            if ($account->is_token_expired) {
                throw new Exception('Access token has expired. Please reconnect your Instagram account.');
            }

            $igAccountId = $account->platform_user_id;
            $accessToken = $account->access_token;

            // Instagram requires media (image or video)
            if (empty($scheduledPost->media)) {
                throw new Exception('Instagram posts require at least one image or video.');
            }

            $mediaUrl = $scheduledPost->media[0];
            $caption = $scheduledPost->content;

            // Step 1: Create media container
            $containerResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media", [
                'image_url' => $mediaUrl,
                'caption' => $caption,
                'access_token' => $accessToken,
            ]);

            if ($containerResponse->failed()) {
                throw new Exception('Failed to create media container: ' . $containerResponse->body());
            }

            $containerId = $containerResponse->json()['id'] ?? null;

            if (!$containerId) {
                throw new Exception('No container ID returned from Instagram');
            }

            // Step 2: Publish the container
            $publishResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media_publish", [
                'creation_id' => $containerId,
                'access_token' => $accessToken,
            ]);

            if ($publishResponse->failed()) {
                throw new Exception('Failed to publish media: ' . $publishResponse->body());
            }

            $result = $publishResponse->json();
            
            // Mark account as used
            $account->markAsUsed();

            return [
                'success' => true,
                'post_id' => $result['id'] ?? null,
                'data' => $result,
            ];

        } catch (Exception $e) {
            Log::error('Instagram publish error: ' . $e->getMessage(), [
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
     * Delete a post from Instagram
     */
    public function deletePost($postId, $accessToken)
    {
        try {
            // Note: Instagram API doesn't support deleting posts via API
            // This would need to be done manually
            Log::warning('Instagram API does not support post deletion via API');
            return false;
        } catch (Exception $e) {
            Log::error('Instagram delete post error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify account connection
     */
    public function verifyAccount(SocialAccount $account)
    {
        try {
            $igAccountId = $account->platform_user_id;
            
            $response = Http::get("{$this->baseUrl}/{$igAccountId}", [
                'fields' => 'id,username',
                'access_token' => $account->access_token,
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Instagram verify account error: ' . $e->getMessage());
            return false;
        }
    }
}


