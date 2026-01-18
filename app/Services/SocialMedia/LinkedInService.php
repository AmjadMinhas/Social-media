<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LinkedInService
{
    protected $baseUrl = 'https://api.linkedin.com/v2';
    protected $authUrl = 'https://www.linkedin.com/oauth/v2';

    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl($state = null)
    {
        $clientId = config('socialmedia.linkedin.client_id');
        $redirectUri = config('socialmedia.linkedin.redirect_uri');
        $scopes = implode(' ', config('socialmedia.linkedin.scopes'));

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state ?? bin2hex(random_bytes(16)),
        ]);

        return "{$this->authUrl}/authorization?{$params}";
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code)
    {
        try {
            $response = Http::asForm()->post("{$this->authUrl}/accessToken", [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('socialmedia.linkedin.client_id'),
                'client_secret' => config('socialmedia.linkedin.client_secret'),
                'redirect_uri' => config('socialmedia.linkedin.redirect_uri'),
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get access token: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('LinkedIn OAuth error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user profile information
     */
    public function getUserProfile($accessToken)
    {
        try {
            // Use OpenID Connect userinfo endpoint
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/userinfo");

            if ($response->failed()) {
                throw new Exception('Failed to get user profile: ' . $response->body());
            }

            $profile = $response->json();
            
            // Format response to match expected structure
            // OpenID Connect returns sub, name, given_name, family_name, email, picture
            return [
                'sub' => $profile['sub'] ?? null,
                'name' => $profile['name'] ?? null,
                'given_name' => $profile['given_name'] ?? null,
                'family_name' => $profile['family_name'] ?? null,
                'email' => $profile['email'] ?? null,
                'picture' => $profile['picture'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('LinkedIn get profile error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's LinkedIn person URN
     */
    public function getPersonUrn($accessToken)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/userinfo");

            if ($response->failed()) {
                throw new Exception('Failed to get person URN: ' . $response->body());
            }

            $data = $response->json();
            return $data['sub'] ?? null;
        } catch (Exception $e) {
            Log::error('LinkedIn get person URN error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish post to LinkedIn
     */
    public function publishPost(SocialAccount $account, ScheduledPost $scheduledPost)
    {
        try {
            // Check if token is expired
            if ($account->is_token_expired) {
                throw new Exception('Access token has expired. Please reconnect your LinkedIn account.');
            }

            $personUrn = $account->platform_data['person_urn'] ?? "urn:li:person:{$account->platform_user_id}";

            // Prepare post data
            $postData = [
                'author' => $personUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $scheduledPost->content,
                        ],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ];

            // Add media if present - parse JSON string if needed
            $media = $scheduledPost->media;
            if (is_string($media)) {
                $media = json_decode($media, true);
            }
            if (empty($media)) {
                $media = [];
            }
            
            if (!empty($media) && is_array($media) && count($media) > 0) {
                $postData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                
                // Upload all images and collect media URNs
                $mediaArray = [];
                foreach ($media as $mediaItem) {
                    // Extract URL from media item (can be string or object {url, thumbnail, is_video})
                    $imageUrl = is_array($mediaItem) && isset($mediaItem['url']) 
                        ? $mediaItem['url'] 
                        : (is_string($mediaItem) ? $mediaItem : null);
                    
                    if (!$imageUrl) {
                        Log::warning('LinkedIn: Invalid media item format', ['media_item' => $mediaItem]);
                        continue;
                    }
                    
                    $mediaUrn = $this->uploadImage($account->access_token, $personUrn, $imageUrl);
                    
                    if ($mediaUrn) {
                        $mediaArray[] = [
                            'status' => 'READY',
                            'media' => $mediaUrn,
                        ];
                    }
                }
                
                // Add all uploaded media to post data
                if (!empty($mediaArray)) {
                    $postData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = $mediaArray;
                    
                    Log::info('LinkedIn publish: Media uploaded successfully', [
                        'total_images' => count($mediaArray),
                    ]);
                } else {
                    Log::warning('LinkedIn publish: No images were successfully uploaded', [
                        'total_attempted' => count($media),
                    ]);
                    // If no images uploaded, remove media category
                    $postData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'NONE';
                }
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->post("{$this->baseUrl}/ugcPosts", $postData);

            if ($response->failed()) {
                throw new Exception('Failed to publish post: ' . $response->body());
            }

            $result = $response->json();
            
            // Mark account as used
            $account->markAsUsed();

            return [
                'success' => true,
                'post_id' => $result['id'] ?? null,
                'data' => $result,
            ];

        } catch (Exception $e) {
            Log::error('LinkedIn publish error: ' . $e->getMessage(), [
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
     * Upload image to LinkedIn
     */
    protected function uploadImage($accessToken, $personUrn, $imageUrl)
    {
        try {
            Log::info('LinkedIn: Starting image upload', ['image_url' => $imageUrl]);
            
            // Step 1: Register upload
            $registerResponse = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/assets?action=registerUpload", [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => $personUrn,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent',
                        ],
                    ],
                ],
            ]);

            if ($registerResponse->failed()) {
                throw new Exception('Failed to register upload: ' . $registerResponse->body());
            }

            $registerData = $registerResponse->json();
            $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
            $asset = $registerData['value']['asset'] ?? null;

            if (!$uploadUrl || !$asset) {
                throw new Exception('Invalid upload registration response');
            }

            Log::info('LinkedIn: Upload registered successfully', [
                'asset' => $asset,
                'has_upload_url' => !empty($uploadUrl)
            ]);

            // Step 2: Get image content - try local file first, then download
            $localFilePath = $this->getLocalFilePathFromUrl($imageUrl);
            $imageContent = null;
            
            Log::info('LinkedIn: Checking file access', [
                'image_url' => $imageUrl,
                'local_file_path' => $localFilePath,
                'local_file_exists' => $localFilePath ? file_exists($localFilePath) : false,
                'local_file_readable' => $localFilePath && file_exists($localFilePath) ? is_readable($localFilePath) : false,
                'local_file_size' => $localFilePath && file_exists($localFilePath) ? filesize($localFilePath) : null,
            ]);
            
            if ($localFilePath && file_exists($localFilePath)) {
                Log::info('LinkedIn: Using local file', [
                    'local_path' => $localFilePath,
                    'file_size' => filesize($localFilePath),
                    'is_readable' => is_readable($localFilePath),
                ]);
                $imageContent = file_get_contents($localFilePath);
                
                if ($imageContent === false) {
                    Log::error('LinkedIn: Failed to read local file', [
                        'local_path' => $localFilePath,
                        'file_exists' => file_exists($localFilePath),
                        'is_readable' => is_readable($localFilePath),
                    ]);
                    throw new Exception('Failed to read local file: ' . $localFilePath);
                }
            } else {
                Log::info('LinkedIn: Downloading image from URL', [
                    'image_url' => $imageUrl,
                    'local_path_attempted' => $localFilePath,
                    'reason' => $localFilePath ? 'file_not_found' : 'no_local_path',
                ]);
                // Use Http client with timeout instead of file_get_contents
                $imageResponse = Http::timeout(30)->get($imageUrl);
                
                if ($imageResponse->failed()) {
                    Log::error('LinkedIn: Failed to download image from URL', [
                        'image_url' => $imageUrl,
                        'status' => $imageResponse->status(),
                        'response' => $imageResponse->body(),
                    ]);
                    throw new Exception('Failed to download image: ' . $imageResponse->body());
                }
                
                $imageContent = $imageResponse->body();
            }
            
            if (empty($imageContent)) {
                throw new Exception('Image content is empty');
            }

            Log::info('LinkedIn: Image content retrieved', [
                'content_size' => strlen($imageContent)
            ]);
            
            // Step 3: Upload image to LinkedIn
            $uploadResponse = Http::timeout(60)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->withBody($imageContent, 'application/octet-stream')
              ->put($uploadUrl);

            if ($uploadResponse->failed()) {
                throw new Exception('Failed to upload image: ' . $uploadResponse->body());
            }

            Log::info('LinkedIn: Image uploaded successfully', ['asset' => $asset]);
            return $asset;

        } catch (Exception $e) {
            Log::error('LinkedIn image upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get local file path from URL (similar to FacebookService)
     */
    protected function getLocalFilePathFromUrl($url)
    {
        try {
            // Handle both string URLs and media objects
            if (is_array($url)) {
                $url = $url['url'] ?? null;
                if (!$url) {
                    Log::warning('LinkedIn getLocalFilePathFromUrl: No URL in array', ['url_array' => $url]);
                    return null;
                }
            }
            
            if (!is_string($url)) {
                Log::warning('LinkedIn getLocalFilePathFromUrl: URL is not a string', ['url_type' => gettype($url)]);
                return null;
            }
            
            Log::info('LinkedIn getLocalFilePathFromUrl: Processing URL', [
                'original_url' => $url,
            ]);
            
            // Extract path from URL (e.g., /media/public/post-scheduler/file.png)
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';
            
            Log::info('LinkedIn getLocalFilePathFromUrl: Parsed URL', [
                'parsed_path' => $path,
                'parsed_url' => $parsedUrl,
            ]);
            
            // Remove /media prefix if present
            if (strpos($path, '/media/') === 0) {
                $path = substr($path, 7); // Remove '/media/' (7 chars)
            }
            
            // Check if it's a public storage path
            if (strpos($path, 'public/') === 0) {
                $relativePath = substr($path, 7); // Remove 'public/' (7 chars)
                $storagePath = storage_path('app/public/' . $relativePath);
                
                Log::info('LinkedIn getLocalFilePathFromUrl: Constructed storage path', [
                    'relative_path' => $relativePath,
                    'storage_path' => $storagePath,
                    'storage_path_exists' => file_exists($storagePath),
                    'storage_path_readable' => file_exists($storagePath) ? is_readable($storagePath) : false,
                ]);
                
                if (file_exists($storagePath)) {
                    return $storagePath;
                }
            }
            
            // Try direct storage path
            $storagePath = storage_path('app/' . ltrim($path, '/'));
            if (file_exists($storagePath)) {
                return $storagePath;
            }
            
            return null;
        } catch (Exception $e) {
            Log::warning('LinkedIn: Failed to get local file path', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete a post from LinkedIn
     */
    public function deletePost($postId, $accessToken)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->delete("{$this->baseUrl}/ugcPosts/{$postId}");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('LinkedIn delete post error: ' . $e->getMessage());
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
            ])->get("{$this->baseUrl}/userinfo");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('LinkedIn verify account error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken($refreshToken)
    {
        try {
            $response = Http::asForm()->post("{$this->authUrl}/accessToken", [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => config('socialmedia.linkedin.client_id'),
                'client_secret' => config('socialmedia.linkedin.client_secret'),
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to refresh token: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('LinkedIn refresh token error: ' . $e->getMessage());
            throw $e;
        }
    }
}


