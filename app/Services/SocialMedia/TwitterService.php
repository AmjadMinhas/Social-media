<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use App\Models\ScheduledPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use GuzzleHttp\Client as GuzzleClient;

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
            'dm.read',        // Required for reading Direct Messages
            'dm.write',       // Required for sending Direct Messages
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

            if (!$codeVerifier) {
                Log::error('Twitter OAuth: Missing code_verifier in session');
                throw new Exception('Missing code verifier. Please try connecting again.');
            }

            $redirectUri = config('socialmedia.twitter.redirect_uri');
            $clientId = config('socialmedia.twitter.client_id');
            $clientSecret = config('socialmedia.twitter.client_secret');
            
            // Validate credentials
            if (empty($clientId) || empty($clientSecret)) {
                throw new Exception('Twitter client credentials are not configured. Please set TWITTER_CLIENT_ID and TWITTER_CLIENT_SECRET in your .env file.');
            }
            
            Log::info('Twitter OAuth: Exchanging code for token', [
                'has_code' => !empty($code),
                'has_code_verifier' => !empty($codeVerifier),
                'redirect_uri' => $redirectUri,
                'has_client_id' => !empty($clientId),
                'has_client_secret' => !empty($clientSecret),
            ]);

            // Twitter OAuth 2.0 token endpoint - must use api.twitter.com, not twitter.com
            $tokenUrl = "https://api.twitter.com/2/oauth2/token";
            
            // Manually create Basic Auth header (Twitter requires explicit Base64 encoding)
            // IMPORTANT: Do NOT URL-encode the credentials before base64 encoding
            $credentials = base64_encode($clientId . ':' . $clientSecret);
            
            // Build form data manually to ensure proper encoding
            $formData = http_build_query([
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'code_verifier' => $codeVerifier,
            ]);
            
            Log::info('Twitter OAuth: Making token request', [
                'url' => $tokenUrl,
                'client_id_length' => strlen($clientId),
                'client_secret_length' => strlen($clientSecret),
                'redirect_uri' => $redirectUri,
                'credentials_header_length' => strlen($credentials),
                'form_data_length' => strlen($formData),
            ]);
            
            // Use Guzzle with built-in auth option (like PayPalService does)
            // This handles Basic Auth header automatically
            $guzzleClient = new GuzzleClient([
                'timeout' => 30,
            ]);
            
            try {
                // Build form data
                // Note: When using Basic Auth, do NOT include client_id in body (Twitter requirement)
                $formParams = [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => $codeVerifier,
                ];
                
                // Create Basic Auth credentials (do NOT URL-encode before base64)
                // Standard Basic Auth format: base64(client_id:client_secret)
                $credentials = base64_encode($clientId . ':' . $clientSecret);
                
                // Debug: Log the exact request we're about to send
                Log::info('Twitter OAuth: About to send cURL request', [
                    'url' => $tokenUrl,
                    'using_curl' => true,
                    'client_id_length' => strlen($clientId),
                    'client_secret_length' => strlen($clientSecret),
                    'credentials_base64_length' => strlen($credentials),
                    'auth_header_preview' => 'Basic ' . substr($credentials, 0, 20) . '...',
                ]);
                
                // Use Laravel Http facade with Basic Auth
                $response = Http::withBasicAuth($clientId, $clientSecret)
                    ->asForm()
                    ->timeout(30)
                    ->post($tokenUrl, $formParams);
                
                $responseStatus = $response->status();
                $responseBody = $response->body();
                $responseData = $response->json();
                
                // Log the actual response
                Log::info('Twitter OAuth: HTTP response received', [
                    'status' => $responseStatus,
                    'response_preview' => substr($responseBody, 0, 200),
                ]);
                
                // Check if response is HTML (error page)
                $contentType = $response->header('Content-Type', '');
                $isHtml = stripos($contentType, 'text/html') !== false || 
                          stripos($responseBody, '<!DOCTYPE') !== false ||
                          stripos($responseBody, '<html') !== false;
                
                if ($isHtml) {
                    // Extract error message from HTML if possible
                    $errorMessage = 'Twitter API returned an HTML error page instead of JSON. ';
                    if (preg_match('/<title>(.*?)<\/title>/is', $responseBody, $matches)) {
                        $errorMessage .= 'Page title: ' . strip_tags($matches[1]) . '. ';
                    }
                    // Try to extract error message from common error page patterns
                    if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $responseBody, $matches)) {
                        $errorMessage .= 'Error: ' . strip_tags($matches[1]) . '. ';
                    }
                    $errorMessage .= 'Please verify your Twitter app credentials, callback URL, and that your app has OAuth 2.0 enabled.';
                    
                    Log::error('Twitter OAuth: Received HTML error page', [
                        'status' => $responseStatus,
                        'content_type' => $contentType,
                        'url' => $tokenUrl,
                        'body_preview' => substr($responseBody, 0, 1000),
                        'redirect_uri' => $redirectUri,
                    ]);
                    
                    throw new Exception($errorMessage);
                }
                
                Log::info('Twitter OAuth token exchange response', [
                    'status' => $responseStatus,
                    'successful' => $responseStatus >= 200 && $responseStatus < 300,
                    'body_length' => strlen($responseBody),
                    'body_preview' => substr($responseBody, 0, 500),
                    'content_type' => $contentType,
                    'json_data' => $responseData,
                ]);
                
                if ($responseStatus < 200 || $responseStatus >= 300) {
                    Log::error('Twitter OAuth token exchange failed', [
                        'status' => $responseStatus,
                        'response' => $responseBody,
                        'json_response' => $responseData,
                    ]);
                    throw new Exception('Failed to get access token: ' . $responseBody);
                }
                
                // Validate response structure
                if ($responseData === null || !is_array($responseData)) {
                    Log::error('Twitter OAuth: Response is not valid JSON', [
                        'status' => $responseStatus,
                        'response_body' => $responseBody,
                        'json_decode_result' => $responseData,
                    ]);
                    throw new Exception('Invalid token response: Response is not valid JSON. Body: ' . substr($responseBody, 0, 200));
                }
                
                return $responseData;
            } catch (Exception $e) {
                // Log the error
                Log::error('Twitter OAuth: HTTP request exception', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Throw exception
                throw $e;
            }

            if (!isset($responseData['access_token'])) {
                Log::error('Twitter OAuth: Invalid token response structure', [
                    'status' => $responseStatus,
                    'response_body' => $responseBody,
                    'response_data' => $responseData,
                    'has_access_token' => isset($responseData['access_token']),
                    'response_keys' => is_array($responseData) ? array_keys($responseData) : 'not_array',
                ]);
                throw new Exception('Invalid token response: missing access_token field. Response: ' . substr($responseBody, 0, 200));
            }

            // Clear code verifier from session
            session()->forget('twitter_code_verifier');

            Log::info('Twitter OAuth: Token exchange successful', [
                'has_access_token' => true,
                'has_refresh_token' => isset($responseData['refresh_token']),
            ]);

            return $responseData;
        } catch (Exception $e) {
            Log::error('Twitter OAuth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get user profile information
     */
    public function getUserProfile($accessToken)
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/users/me", [
                'user.fields' => 'id,name,username,profile_image_url',
            ]);

            if ($response->failed()) {
                $errorBody = $response->body();
                Log::error('Twitter get profile failed', [
                    'status' => $response->status(),
                    'response' => $errorBody,
                ]);
                throw new Exception('Failed to get user profile: ' . $errorBody);
            }

            $responseData = $response->json();
            
            if (!isset($responseData['data'])) {
                Log::error('Twitter profile response missing data', [
                    'response' => $responseData,
                ]);
                throw new Exception('Invalid profile response: missing data field');
            }

            return $responseData['data'];
        } catch (Exception $e) {
            Log::error('Twitter get profile error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
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
            $clientId = config('socialmedia.twitter.client_id');
            $clientSecret = config('socialmedia.twitter.client_secret');
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new Exception('Twitter client credentials are not configured.');
            }
            
            // Manually create Basic Auth header
            $credentials = base64_encode($clientId . ':' . $clientSecret);
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Basic ' . $credentials,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->asForm()
                ->post("https://api.twitter.com/2/oauth2/token", [
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




















