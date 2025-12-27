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
    public function getAuthorizationUrl($state = null, $forceReauth = false)
    {
        $appId = config('socialmedia.instagram.app_id');
        $redirectUri = config('socialmedia.instagram.redirect_uri');
        
        $scopes = [
            'instagram_basic',
            'instagram_content_publish',
            'pages_show_list',
            'pages_read_engagement',
        ];

        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'state' => $state ?? bin2hex(random_bytes(16)),
            'response_type' => 'code',
        ];

        // Add parameters to force fresh authorization for Instagram
        // This ensures Instagram permissions are requested separately from Facebook
        if ($forceReauth) {
            $params['auth_type'] = 'rerequest';
            $params['prompt'] = 'consent';
        }

        return "https://www.facebook.com/{$this->apiVersion}/dialog/oauth?" . http_build_query($params);
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
            // First check what permissions the token has
            $debugResponse = Http::get("{$this->baseUrl}/me/permissions", [
                'access_token' => $accessToken,
            ]);
            
            if ($debugResponse->successful()) {
                $permissions = $debugResponse->json();
                Log::info('Instagram: Token permissions', [
                    'permissions' => $permissions,
                    'has_pages_show_list' => collect($permissions['data'] ?? [])->contains(function($perm) {
                        return ($perm['permission'] ?? '') === 'pages_show_list' && ($perm['status'] ?? '') === 'granted';
                    }),
                ]);
            }
            
            // First get Facebook pages
            $pagesResponse = Http::get("{$this->baseUrl}/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token',
            ]);

            Log::info('Instagram: Pages API response', [
                'status' => $pagesResponse->status(),
                'successful' => $pagesResponse->successful(),
                'body' => $pagesResponse->body(),
                'json' => $pagesResponse->json(),
            ]);

            if ($pagesResponse->failed()) {
                Log::error('Instagram: Failed to get pages', [
                    'status' => $pagesResponse->status(),
                    'body' => $pagesResponse->body(),
                ]);
                throw new Exception('Failed to get pages: ' . $pagesResponse->body());
            }

            $responseData = $pagesResponse->json();
            $pages = $responseData['data'] ?? [];
            
            Log::info('Instagram: Pages retrieved', [
                'pages_count' => count($pages),
                'page_ids' => array_column($pages, 'id'),
                'page_names' => array_column($pages, 'name'),
                'full_response' => $responseData,
            ]);

            $instagramAccounts = [];

            // For each page, get connected Instagram account
            foreach ($pages as $page) {
                Log::info('Instagram: Checking page for Instagram account', [
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                ]);

                $igResponse = Http::get("{$this->baseUrl}/{$page['id']}", [
                    'fields' => 'instagram_business_account{id,username}',
                    'access_token' => $page['access_token'],
                ]);

                if ($igResponse->failed()) {
                    Log::warning('Instagram: Failed to get Instagram account for page', [
                        'page_id' => $page['id'],
                        'status' => $igResponse->status(),
                        'body' => $igResponse->body(),
                    ]);
                    continue;
                }

                $igData = $igResponse->json();
                Log::info('Instagram: Page data retrieved', [
                    'page_id' => $page['id'],
                    'has_instagram_business_account' => isset($igData['instagram_business_account']),
                    'data' => $igData,
                ]);
                
                if (isset($igData['instagram_business_account'])) {
                    $igBusinessAccount = $igData['instagram_business_account'];
                    $igAccountId = is_array($igBusinessAccount) ? ($igBusinessAccount['id'] ?? null) : $igBusinessAccount;
                    
                    if (!$igAccountId) {
                        Log::warning('Instagram: Instagram business account ID not found in response', [
                            'page_id' => $page['id'],
                            'instagram_business_account' => $igBusinessAccount,
                        ]);
                        continue;
                    }
                    
                    Log::info('Instagram: Instagram Business account found', [
                        'page_id' => $page['id'],
                        'ig_account_id' => $igAccountId,
                    ]);
                    
                    // Get Instagram account details
                    $detailsResponse = Http::get("{$this->baseUrl}/{$igAccountId}", [
                        'fields' => 'id,username,profile_picture_url',
                        'access_token' => $page['access_token'],
                    ]);

                    if ($detailsResponse->failed()) {
                        Log::warning('Instagram: Failed to get Instagram account details', [
                            'ig_account_id' => $igAccountId,
                            'status' => $detailsResponse->status(),
                            'body' => $detailsResponse->body(),
                        ]);
                        continue;
                    }

                    $details = $detailsResponse->json();
                    Log::info('Instagram: Account details retrieved', [
                        'ig_account_id' => $igAccountId,
                        'username' => $details['username'] ?? null,
                    ]);
                    
                    $instagramAccounts[] = [
                        'id' => $igAccountId,
                        'username' => $details['username'] ?? 'Unknown',
                        'profile_picture' => $details['profile_picture_url'] ?? null,
                        'page_id' => $page['id'],
                        'page_name' => $page['name'],
                        'access_token' => $page['access_token'],
                    ];
                } else {
                    Log::info('Instagram: No Instagram Business account linked to page', [
                        'page_id' => $page['id'],
                        'page_name' => $page['name'],
                    ]);
                }
            }

            Log::info('Instagram: Final accounts list', [
                'accounts_count' => count($instagramAccounts),
                'account_ids' => array_column($instagramAccounts, 'id'),
            ]);

            return $instagramAccounts;
        } catch (Exception $e) {
            Log::error('Instagram get accounts error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Publish post to Instagram
     */
    public function publishPost(SocialAccount $account, ScheduledPost $scheduledPost)
    {
        try {
            Log::info('Instagram publish: Starting', [
                'account_id' => $account->id,
                'post_id' => $scheduledPost->id,
                'ig_account_id' => $account->platform_user_id,
            ]);

            // Check if token is expired
            if ($account->is_token_expired) {
                throw new Exception('Access token has expired. Please reconnect your Instagram account.');
            }

            $igAccountId = $account->platform_user_id;
            $accessToken = $account->access_token;

            // Parse media - handle both JSON string and array
            $media = $scheduledPost->media;
            if (is_string($media)) {
                $media = json_decode($media, true);
            }
            if (empty($media) || !is_array($media)) {
                $media = [];
            }

            // Instagram requires media (image or video)
            if (empty($media)) {
                throw new Exception('Instagram posts require at least one image or video.');
            }

            $caption = $scheduledPost->content ?? '';

            Log::info('Instagram publish: Media info', [
                'media_count' => count($media),
                'has_caption' => !empty($caption),
            ]);

            // If multiple images, create carousel post (Instagram supports up to 10 images)
            if (count($media) > 1) {
                return $this->publishCarouselPost($igAccountId, $accessToken, $media, $caption, $account);
            }

            // Single image/video post
            $mediaUrl = $media[0];
            
            // Instagram requires publicly accessible URLs - ensure URL is correct
            // If it's a local ngrok URL, try to get the public URL
            $publicMediaUrl = $this->ensurePublicUrl($mediaUrl);
            
            Log::info('Instagram publish: Using media URL', [
                'original_url' => $mediaUrl,
                'public_url' => $publicMediaUrl,
            ]);
            
            // Determine if it's a video
            $isVideo = str_contains(strtolower($mediaUrl), '.mp4') || 
                      str_contains(strtolower($mediaUrl), 'video') ||
                      str_contains(strtolower($mediaUrl), '.mov');

            if ($isVideo) {
                // For video, use video endpoint
                $containerResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media", [
                    'media_type' => 'VIDEO',
                    'video_url' => $publicMediaUrl,
                    'caption' => $caption,
                    'access_token' => $accessToken,
                ]);
            } else {
                // For image
                $containerResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media", [
                    'image_url' => $publicMediaUrl,
                    'caption' => $caption,
                    'access_token' => $accessToken,
                ]);
            }

            if ($containerResponse->failed()) {
                $errorBody = $containerResponse->body();
                $errorData = json_decode($errorBody, true);
                $errorCode = $errorData['error']['code'] ?? null;
                $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
                
                // Check if it's the media download error (common with ngrok/local URLs)
                if ($errorCode == 9004 || str_contains($errorMessage, 'Media download has failed')) {
                    $helpfulMessage = "Instagram cannot fetch the image from the provided URL. ";
                    $helpfulMessage .= "This often happens with ngrok URLs or local URLs that are not publicly accessible. ";
                    $helpfulMessage .= "For production, please use AWS S3 or another CDN that provides publicly accessible URLs. ";
                    $helpfulMessage .= "Original error: " . $errorMessage;
                    
                    Log::error('Instagram publish: Media download failed', [
                        'original_url' => $mediaUrl,
                        'public_url' => $publicMediaUrl,
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'helpful_message' => $helpfulMessage,
                    ]);
                    
                    throw new Exception($helpfulMessage);
                }
                
                Log::error('Instagram publish: Failed to create container', [
                    'response' => $errorBody,
                    'status' => $containerResponse->status(),
                    'error_code' => $errorCode,
                ]);
                throw new Exception('Failed to create media container: ' . $errorBody);
            }

            $containerData = $containerResponse->json();
            $containerId = $containerData['id'] ?? null;

            if (!$containerId) {
                throw new Exception('No container ID returned from Instagram');
            }

            Log::info('Instagram publish: Container created', [
                'container_id' => $containerId,
            ]);

            // Wait a bit for video processing if it's a video
            if ($isVideo) {
                sleep(3);
            }

            // Step 2: Publish the container
            $publishResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media_publish", [
                'creation_id' => $containerId,
                'access_token' => $accessToken,
            ]);

            if ($publishResponse->failed()) {
                Log::error('Instagram publish: Failed to publish', [
                    'response' => $publishResponse->body(),
                    'status' => $publishResponse->status(),
                    'container_id' => $containerId,
                ]);
                throw new Exception('Failed to publish media: ' . $publishResponse->body());
            }

            $result = $publishResponse->json();
            
            Log::info('Instagram publish: Success', [
                'post_id' => $result['id'] ?? null,
            ]);
            
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
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Publish carousel post (multiple images) to Instagram
     */
    protected function publishCarouselPost($igAccountId, $accessToken, array $mediaUrls, $caption, SocialAccount $account)
    {
        try {
            Log::info('Instagram publish: Creating carousel post', [
                'media_count' => count($mediaUrls),
            ]);

            // Limit to 10 images (Instagram max)
            $mediaUrls = array_slice($mediaUrls, 0, 10);
            $children = [];

            // Step 1: Create containers for each image
            foreach ($mediaUrls as $index => $imageUrl) {
                Log::info('Instagram publish: Creating container for image', [
                    'index' => $index + 1,
                    'total' => count($mediaUrls),
                ]);

                // Ensure URL is publicly accessible
                $publicImageUrl = $this->ensurePublicUrl($imageUrl);
                
                $containerResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media", [
                    'image_url' => $publicImageUrl,
                    'is_carousel_item' => true,
                    'access_token' => $accessToken,
                ]);

                if ($containerResponse->failed()) {
                    Log::error('Instagram publish: Failed to create carousel item container', [
                        'index' => $index + 1,
                        'response' => $containerResponse->body(),
                    ]);
                    throw new Exception('Failed to create carousel item container: ' . $containerResponse->body());
                }

                $containerData = $containerResponse->json();
                $containerId = $containerData['id'] ?? null;

                if (!$containerId) {
                    throw new Exception("No container ID returned for carousel item {$index}");
                }

                $children[] = $containerId;
            }

            // Step 2: Create carousel container with all children
            $carouselResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media", [
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $children),
                'caption' => $caption,
                'access_token' => $accessToken,
            ]);

            if ($carouselResponse->failed()) {
                Log::error('Instagram publish: Failed to create carousel container', [
                    'response' => $carouselResponse->body(),
                ]);
                throw new Exception('Failed to create carousel container: ' . $carouselResponse->body());
            }

            $carouselData = $carouselResponse->json();
            $carouselContainerId = $carouselData['id'] ?? null;

            if (!$carouselContainerId) {
                throw new Exception('No carousel container ID returned from Instagram');
            }

            Log::info('Instagram publish: Carousel container created', [
                'container_id' => $carouselContainerId,
            ]);

            // Wait a bit for processing
            sleep(3);

            // Step 3: Publish the carousel
            $publishResponse = Http::post("{$this->baseUrl}/{$igAccountId}/media_publish", [
                'creation_id' => $carouselContainerId,
                'access_token' => $accessToken,
            ]);

            if ($publishResponse->failed()) {
                Log::error('Instagram publish: Failed to publish carousel', [
                    'response' => $publishResponse->body(),
                    'container_id' => $carouselContainerId,
                ]);
                throw new Exception('Failed to publish carousel: ' . $publishResponse->body());
            }

            $result = $publishResponse->json();
            
            Log::info('Instagram publish: Carousel published successfully', [
                'post_id' => $result['id'] ?? null,
            ]);

            $account->markAsUsed();

            return [
                'success' => true,
                'post_id' => $result['id'] ?? null,
                'data' => $result,
            ];

        } catch (Exception $e) {
            Log::error('Instagram carousel publish error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
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

    /**
     * Ensure media URL is publicly accessible for Instagram
     * Instagram requires URLs that are publicly accessible and return images directly
     */
    protected function ensurePublicUrl($url)
    {
        // If it's already a full URL starting with http/https, return as is
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        
        // If it's a relative path, prepend the app URL
        return rtrim(config('app.url'), '/') . '/' . ltrim($url, '/');
    }
}


















