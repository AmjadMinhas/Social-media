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
    public function getAuthorizationUrl($state = null, $forceReauth = false)
    {
        $appId = config('socialmedia.facebook.app_id');
        $redirectUri = config('socialmedia.facebook.redirect_uri');
        $permissions = implode(',', config('socialmedia.facebook.permissions'));

        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => $permissions,
            'state' => $state ?? bin2hex(random_bytes(16)),
            'response_type' => 'code',
        ];

        // Add prompt parameter to force re-authorization if needed
        // This forces Facebook to show the authorization dialog even if already connected
        if ($forceReauth) {
            $params['prompt'] = 'consent';
            $params['auth_type'] = 'rerequest';
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
            Log::info('Facebook getUserPages: Requesting pages', [
                'base_url' => $this->baseUrl,
                'has_token' => !empty($accessToken),
                'token_preview' => substr($accessToken, 0, 20) . '...'
            ]);
            
            // First, get user ID to verify token works
            $userResponse = Http::get("{$this->baseUrl}/me", [
                'access_token' => $accessToken,
                'fields' => 'id,name'
            ]);
            
            $userId = null;
            if ($userResponse->successful()) {
                $userData = $userResponse->json();
                $userId = $userData['id'] ?? null;
                Log::info('Facebook getUserPages: User info retrieved', [
                    'user_id' => $userId,
                    'user_name' => $userData['name'] ?? null
                ]);
            }
            
            // Try /me/accounts first (standard endpoint)
            $response = Http::get("{$this->baseUrl}/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token,picture,category',
                'limit' => 100, // Get up to 100 pages
            ]);
            
            $data = $response->json();
            $pages = $data['data'] ?? [];
            
            Log::info('Facebook getUserPages: /me/accounts response', [
                'status' => $response->status(),
                'has_data' => isset($data['data']),
                'pages_count' => count($pages),
                'full_response' => $data
            ]);
            
            // If no pages found, try alternative approaches
            if (empty($pages)) {
                // Try /me/pages as alternative
                Log::info('Facebook getUserPages: No pages from /me/accounts, trying /me/pages');
                $response2 = Http::get("{$this->baseUrl}/me/pages", [
                    'access_token' => $accessToken,
                    'fields' => 'id,name,access_token,picture',
                    'limit' => 100,
                ]);
                
                if ($response2->successful()) {
                    $data2 = $response2->json();
                    $pages2 = $data2['data'] ?? [];
                    Log::info('Facebook getUserPages: /me/pages response', [
                        'pages_count' => count($pages2),
                        'full_response' => $data2
                    ]);
                    if (!empty($pages2)) {
                        Log::info('Facebook getUserPages: Found pages via /me/pages', ['count' => count($pages2)]);
                        $pages = $pages2;
                        $data = $data2;
                    }
                }
                
                // If still no pages, try with user ID directly
                if (empty($pages) && $userId) {
                    Log::info('Facebook getUserPages: Trying with user ID', ['user_id' => $userId]);
                    $response3 = Http::get("{$this->baseUrl}/{$userId}/accounts", [
                        'access_token' => $accessToken,
                        'fields' => 'id,name,access_token,picture',
                        'limit' => 100,
                    ]);
                    
                    if ($response3->successful()) {
                        $data3 = $response3->json();
                        $pages3 = $data3['data'] ?? [];
                        Log::info('Facebook getUserPages: User ID accounts response', [
                            'pages_count' => count($pages3),
                            'full_response' => $data3
                        ]);
                        if (!empty($pages3)) {
                            $pages = $pages3;
                            $data = $data3;
                        }
                    }
                }
            }

            Log::info('Facebook getUserPages: Response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'failed' => $response->failed(),
                'pages_count' => count($pages)
            ]);

            if ($response->failed() && empty($pages)) {
                $errorBody = $response->body();
                $errorJson = $response->json();
                
                Log::error('Facebook get pages error', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'json' => $errorJson,
                    'error_type' => $errorJson['error']['type'] ?? null,
                    'error_message' => $errorJson['error']['message'] ?? null,
                    'error_code' => $errorJson['error']['code'] ?? null
                ]);
                
                throw new Exception('Failed to get user pages: ' . ($errorJson['error']['message'] ?? $errorBody));
            }
            
            // Handle pagination if there are more pages
            if (isset($data['paging']['next'])) {
                Log::info('Facebook getUserPages: More pages available, fetching...');
                // For now, we'll just log it - you can implement pagination if needed
                // Most users won't have more than 100 pages
            }
            
            Log::info('Facebook getUserPages: Pages retrieved', [
                'pages_count' => count($pages),
                'has_data_key' => isset($data['data']),
                'response_keys' => array_keys($data ?? []),
                'full_response' => $data // Log full response for debugging
            ]);

            return $pages;
        } catch (Exception $e) {
            Log::error('Facebook get pages error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
            $media = $scheduledPost->media;
            
            // Ensure media is an array (it might be JSON string or null)
            if (is_string($media)) {
                $media = json_decode($media, true);
            }
            
            // Handle null or empty media
            if (empty($media)) {
                $media = [];
            }
            
            Log::info('Facebook publish: Processing media', [
                'raw_media' => $scheduledPost->getRawOriginal('media'),
                'has_media' => !empty($media),
                'media_count' => is_array($media) ? count($media) : 0,
                'media_type' => gettype($media),
                'media_content' => $media
            ]);
            
            if (!empty($media) && is_array($media) && count($media) > 0) {
                $firstMedia = $media[0];
                
                // Check if it's a video (MP4) or image
                $isVideo = str_contains($firstMedia, '.mp4') || str_contains(strtolower($firstMedia), 'video');
                
                if ($isVideo) {
                    // For video, use video endpoint
                    $postData['file_url'] = $firstMedia;
                    $postData['description'] = $scheduledPost->content;
                    $endpoint = "{$this->baseUrl}/{$pageId}/videos";
                    Log::info('Facebook publish: Using videos endpoint for video', [
                        'video_url' => $firstMedia
                    ]);
                } else {
                    // For single image - Facebook requires file upload, not URL
                    if (count($media) === 1) {
                        // Get local file path from URL
                        $localFilePath = $this->getLocalFilePathFromUrl($firstMedia);
                        
                        if ($localFilePath && file_exists($localFilePath)) {
                            // Use local file directly
                            try {
                                $uploadData = [
                                    'message' => $scheduledPost->content,
                                    'access_token' => $account->access_token,
                                ];
                                
                                $endpoint = "{$this->baseUrl}/{$pageId}/photos";
                                
                                Log::info('Facebook publish: Using photos endpoint with local file', [
                                    'image_url' => $firstMedia,
                                    'local_path' => $localFilePath,
                                    'endpoint' => $endpoint
                                ]);
                                
                                // Upload as multipart form data
                                $response = Http::attach('source', file_get_contents($localFilePath), basename($firstMedia))
                                    ->post($endpoint, $uploadData);
                            } catch (\Exception $e) {
                                Log::error('Facebook publish: Failed to upload local file', [
                                    'error' => $e->getMessage(),
                                    'local_path' => $localFilePath
                                ]);
                                throw $e;
                            }
                        } else {
                            // Try downloading if local file not found
                            try {
                                Log::info('Facebook publish: Local file not found, downloading from URL', [
                                    'image_url' => $firstMedia
                                ]);
                                
                                $imageContent = Http::timeout(30)->get($firstMedia)->body();
                                $tempFile = tmpfile();
                                $tempPath = stream_get_meta_data($tempFile)['uri'];
                                file_put_contents($tempPath, $imageContent);
                                
                                $uploadData = [
                                    'message' => $scheduledPost->content,
                                    'access_token' => $account->access_token,
                                ];
                                
                                $endpoint = "{$this->baseUrl}/{$pageId}/photos";
                                
                                $response = Http::attach('source', file_get_contents($tempPath), basename($firstMedia))
                                    ->post($endpoint, $uploadData);
                                
                                @unlink($tempPath);
                            } catch (\Exception $e) {
                                Log::error('Facebook publish: Failed to download/upload image', [
                                    'error' => $e->getMessage(),
                                    'image_url' => $firstMedia
                                ]);
                                throw $e;
                            }
                        }
                    } 
                    // For multiple images - Facebook supports multiple images in one post
                    else if (count($media) > 1) {
                        Log::info('Facebook publish: Processing multiple images', [
                            'total_images' => count($media),
                        ]);
                        
                        $attachedMedia = [];
                        
                        // Step 1: Upload each image as unpublished photo
                        foreach ($media as $imageUrl) {
                            $localFilePath = $this->getLocalFilePathFromUrl($imageUrl);
                            
                            if ($localFilePath && file_exists($localFilePath)) {
                                try {
                                    $uploadData = [
                                        'published' => false,
                                        'access_token' => $account->access_token,
                                    ];
                                    
                                    $uploadEndpoint = "{$this->baseUrl}/{$pageId}/photos";
                                    
                                    $uploadResponse = Http::attach('source', file_get_contents($localFilePath), basename($imageUrl))
                                        ->post($uploadEndpoint, $uploadData);
                                    
                                    if ($uploadResponse->successful()) {
                                        $photoData = $uploadResponse->json();
                                        $photoId = $photoData['id'] ?? null;
                                        
                                        if ($photoId) {
                                            $attachedMedia[] = json_encode(['media_fbid' => $photoId]);
                                            Log::info('Facebook publish: Photo uploaded (unpublished)', [
                                                'photo_id' => $photoId,
                                            ]);
                                        }
                                    } else {
                                        Log::warning('Facebook publish: Failed to upload photo', [
                                            'image_url' => $imageUrl,
                                            'response' => $uploadResponse->body(),
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Facebook publish: Exception uploading photo', [
                                        'image_url' => $imageUrl,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            } else {
                                // Try downloading if local file not found
                                try {
                                    $imageContent = Http::timeout(30)->get($imageUrl)->body();
                                    $tempFile = tmpfile();
                                    $tempPath = stream_get_meta_data($tempFile)['uri'];
                                    file_put_contents($tempPath, $imageContent);
                                    
                                    $uploadData = [
                                        'published' => false,
                                        'access_token' => $account->access_token,
                                    ];
                                    
                                    $uploadEndpoint = "{$this->baseUrl}/{$pageId}/photos";
                                    
                                    $uploadResponse = Http::attach('source', file_get_contents($tempPath), basename($imageUrl))
                                        ->post($uploadEndpoint, $uploadData);
                                    
                                    @unlink($tempPath);
                                    
                                    if ($uploadResponse->successful()) {
                                        $photoData = $uploadResponse->json();
                                        $photoId = $photoData['id'] ?? null;
                                        
                                        if ($photoId) {
                                            $attachedMedia[] = json_encode(['media_fbid' => $photoId]);
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Facebook publish: Exception downloading/uploading photo', [
                                        'image_url' => $imageUrl,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                        
                        // Step 2: Create post with all attached media
                        if (!empty($attachedMedia)) {
                            $postData = [
                                'message' => $scheduledPost->content,
                                'attached_media' => $attachedMedia,
                                'access_token' => $account->access_token,
                            ];
                            
                            $endpoint = "{$this->baseUrl}/{$pageId}/feed";
                            
                            Log::info('Facebook publish: Creating post with multiple images', [
                                'images_count' => count($attachedMedia),
                                'endpoint' => $endpoint,
                            ]);
                            
                            $response = Http::post($endpoint, $postData);
                        } else {
                            throw new Exception('Failed to upload any images for multi-image post');
                        }
                    }
                }
            } else {
                // Text only post
                $endpoint = "{$this->baseUrl}/{$pageId}/feed";
                Log::info('Facebook publish: Using feed endpoint for text-only post');
                $response = Http::post($endpoint, $postData);
            }

            // Ensure response is defined
            if (!isset($response)) {
                Log::error('Facebook publish: Response not defined, endpoint not called');
                throw new Exception('Failed to send request to Facebook API');
            }

            if ($response->failed()) {
                $errorBody = $response->body();
                Log::error('Facebook publish: Request failed', [
                    'status' => $response->status(),
                    'error' => $errorBody,
                    'endpoint' => $endpoint,
                    'post_id' => $scheduledPost->id
                ]);
                throw new Exception('Failed to publish post: ' . $errorBody);
            }

            $result = $response->json();
            
            Log::info('Facebook publish: Success', [
                'post_id' => $scheduledPost->id,
                'facebook_post_id' => $result['id'] ?? $result['post_id'] ?? null,
                'response' => $result
            ]);
            
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

    /**
     * Send a message via Facebook Messenger
     */
    public function sendMessage(SocialAccount $account, $recipientId, $message)
    {
        try {
            $pageId = $account->platform_data['page_id'] ?? $account->platform_user_id;
            $accessToken = $account->access_token;

            if (!$pageId || !$accessToken) {
                throw new Exception('Missing page ID or access token');
            }

            // Facebook Messenger API endpoint: Use /me/messages with page access token
            // The page access token automatically scopes to the correct page
            $endpoint = "{$this->baseUrl}/me/messages";

            $requestData = [
                'recipient' => [
                    'id' => $recipientId
                ],
                'message' => [
                    'text' => $message
                ],
                'access_token' => $accessToken
            ];

            Log::info('Facebook Messenger: Sending message', [
                'page_id' => $pageId,
                'recipient_id' => $recipientId,
                'message_preview' => substr($message, 0, 50)
            ]);

            $response = Http::post($endpoint, $requestData);

            if ($response->failed()) {
                $errorBody = $response->body();
                $errorJson = json_decode($errorBody, true);
                $errorMessage = $errorJson['error']['message'] ?? 'Unknown error';
                $errorCode = $errorJson['error']['code'] ?? null;

                Log::error('Facebook Messenger: Failed to send message', [
                    'status' => $response->status(),
                    'error' => $errorBody,
                    'recipient_id' => $recipientId,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage
                ]);

                throw new Exception("Failed to send message: {$errorBody}");
            }

            $result = $response->json();

            Log::info('Facebook Messenger: Message sent successfully', [
                'message_id' => $result['message_id'] ?? null,
                'recipient_id' => $recipientId
            ]);

            return [
                'success' => true,
                'message_id' => $result['message_id'] ?? null,
                'data' => $result
            ];

        } catch (Exception $e) {
            Log::error('Facebook Messenger send error: ' . $e->getMessage(), [
                'account_id' => $account->id,
                'recipient_id' => $recipientId
            ]);
            throw $e;
        }
    }

    /**
     * Get local file path from URL
     */
    protected function getLocalFilePathFromUrl($url)
    {
        try {
            // Extract path from URL (e.g., /media/public/post-scheduler/file.png)
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';
            
            // Remove /media prefix if present
            if (strpos($path, '/media/') === 0) {
                $path = substr($path, 7); // Remove '/media/' (7 chars)
            }
            
            // Path is now: public/post-scheduler/file.png
            // Storage path: storage/app/public/post-scheduler/file.png
            $storagePath = storage_path('app/' . $path);
            
            // Also check public symlink path: public/storage/post-scheduler/file.png
            $publicPath = public_path('storage/' . ltrim($path, 'public/'));
            
            Log::info('Facebook publish: Extracting local path', [
                'url' => $url,
                'extracted_path' => $path,
                'storage_path' => $storagePath,
                'public_path' => $publicPath,
                'storage_exists' => file_exists($storagePath),
                'public_exists' => file_exists($publicPath)
            ]);
            
            if (file_exists($storagePath)) {
                return $storagePath;
            } elseif (file_exists($publicPath)) {
                return $publicPath;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Facebook publish: Failed to extract local path', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}









