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
            // Get basic profile using v2 API (doesn't require openid scope)
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/me");

            if ($response->failed()) {
                throw new Exception('Failed to get user profile: ' . $response->body());
            }

            $profile = $response->json();
            
            // Get email separately if needed
            $emailResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/emailAddress?q=members&projection=(elements*(handle~))");
            
            $email = null;
            if ($emailResponse->successful()) {
                $emailData = $emailResponse->json();
                $email = $emailData['elements'][0]['handle~']['emailAddress'] ?? null;
            }
            
            // Format response to match expected structure
            return [
                'sub' => $profile['id'] ?? null,
                'name' => ($profile['localizedFirstName'] ?? '') . ' ' . ($profile['localizedLastName'] ?? ''),
                'given_name' => $profile['localizedFirstName'] ?? null,
                'family_name' => $profile['localizedLastName'] ?? null,
                'email' => $email,
                'picture' => $profile['profilePicture']['displayImage~']['elements'][0]['identifiers'][0]['identifier'] ?? null,
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
            ])->get("{$this->baseUrl}/me");

            if ($response->failed()) {
                throw new Exception('Failed to get person URN: ' . $response->body());
            }

            $data = $response->json();
            return $data['id'] ?? null;
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

            // Add media if present
            if (!empty($scheduledPost->media)) {
                $postData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                
                // For now, handle single image
                // Multi-image requires more complex implementation
                if (count($scheduledPost->media) === 1) {
                    $mediaUrn = $this->uploadImage($account->access_token, $personUrn, $scheduledPost->media[0]);
                    
                    if ($mediaUrn) {
                        $postData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                            [
                                'status' => 'READY',
                                'media' => $mediaUrn,
                            ],
                        ];
                    }
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
            // Step 1: Register upload
            $registerResponse = Http::withHeaders([
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

            // Step 2: Upload image
            $imageContent = file_get_contents($imageUrl);
            
            $uploadResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->withBody($imageContent, 'application/octet-stream')
              ->put($uploadUrl);

            if ($uploadResponse->failed()) {
                throw new Exception('Failed to upload image');
            }

            return $asset;

        } catch (Exception $e) {
            Log::error('LinkedIn image upload error: ' . $e->getMessage());
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
            ])->get("{$this->baseUrl}/me");

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


