<?php

namespace App\Services\SocialMedia;

use App\Models\Contact;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\Organization;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Twitter/X Direct Messages Service
 * Handles fetching and sending messages via Twitter Direct Messages
 */
class TwitterMessengerService
{
    protected $baseUrl = 'https://api.twitter.com/2';
    protected $baseUrlV1 = 'https://api.twitter.com/1.1';

    /**
     * Fetch messages from Twitter Direct Messages
     */
    public function fetchMessages(SocialAccount $account, $since = null)
    {
        try {
            $accessToken = $account->access_token;

            if (!$accessToken) {
                Log::error('Twitter DM fetch: No access token found', [
                    'account_id' => $account->id,
                    'platform' => $account->platform,
                ]);
                return [];
            }

            // Twitter DM endpoints require Project enrollment for v2
            // Since posting works, let's try alternative approaches
            $userId = $account->platform_user_id;
            
            // Try multiple endpoint variations
            $endpointsToTry = [
                // Try with user context
                "{$this->baseUrl}/users/{$userId}/dm_events",
                // Try direct endpoint
                "{$this->baseUrl}/dm_events",
                // Try conversations endpoint
                "{$this->baseUrl}/dm_conversations",
            ];
            
            $params = [
                'max_results' => 50,
                'dm_event.fields' => 'id,text,created_timestamp,sender_id,recipient_id',
            ];
            
            if ($since) {
                $params['start_time'] = $since;
            }
            
            foreach ($endpointsToTry as $endpoint) {
                Log::info('Twitter DM fetch: Trying endpoint', [
                    'account_id' => $account->id,
                    'endpoint' => $endpoint,
                ]);
                
                try {
                    $response = Http::timeout(30)->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                    ])->get($endpoint, $params);
                    
                    $status = $response->status();
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        $events = $data['data'] ?? $data['events'] ?? [];
                        
                        if (!empty($events)) {
                            Log::info('Twitter DM fetch: Success', [
                                'endpoint' => $endpoint,
                                'events_count' => count($events),
                            ]);
                            return $events;
                        }
                    } else {
                        Log::info('Twitter DM fetch: Endpoint failed', [
                            'endpoint' => $endpoint,
                            'status' => $status,
                            'response' => substr($response->body(), 0, 200),
                        ]);
                    }
                } catch (Exception $e) {
                    Log::warning('Twitter DM fetch: Endpoint exception', [
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // If all endpoints failed, try the standard one for error handling
            Log::info('Twitter DM fetch: All alternative endpoints failed, trying standard', [
                'account_id' => $account->id,
                'endpoint' => "{$this->baseUrl}/dm_events",
            ]);
            
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/dm_events", $params);

            $status = $response->status();
            $body = $response->body();

            Log::info('Twitter DM fetch: v2 API response', [
                'status' => $status,
                'body_preview' => substr($body, 0, 500),
            ]);

            // If v2 fails with Project error, try v1.1 as fallback
            if ($response->failed()) {
                $errorData = $response->json();
                $errorDetail = $errorData['detail'] ?? $errorData['title'] ?? $body;
                
                // Check if it's the Project enrollment error - try v1.1 fallback
                if ($status === 403 && (str_contains($errorDetail, 'Project') || str_contains($errorDetail, 'client-not-enrolled'))) {
                    Log::warning('Twitter DM fetch: v2 requires Project, trying v1.1 fallback', [
                        'account_id' => $account->id,
                    ]);
                    
                    // Try v1.1 API endpoint - some endpoints work with OAuth 2.0 Bearer tokens
                    try {
                        $v1Params = ['count' => 50];
                        if ($since) {
                            $v1Params['since_id'] = $since;
                        }
                        
                        Log::info('Twitter DM fetch: Trying v1.1 API', [
                            'endpoint' => "{$this->baseUrlV1}/direct_messages/events/list.json",
                        ]);
                        
                        $v1Response = Http::timeout(30)->withHeaders([
                            'Authorization' => "Bearer {$accessToken}",
                        ])->get("{$this->baseUrlV1}/direct_messages/events/list.json", $v1Params);
                        
                        $v1Status = $v1Response->status();
                        $v1Body = $v1Response->body();
                        
                        Log::info('Twitter DM fetch: v1.1 API response', [
                            'status' => $v1Status,
                            'body_preview' => substr($v1Body, 0, 500),
                        ]);
                        
                        if ($v1Response->successful()) {
                            $v1Data = $v1Response->json();
                            // v1.1 returns {events: [...]}
                            $events = $v1Data['events'] ?? [];
                            
                            Log::info('Twitter DM fetch: v1.1 API success', [
                                'events_count' => count($events),
                                'account_id' => $account->id,
                            ]);
                            
                            return $events;
                        } else {
                            Log::warning('Twitter DM fetch: v1.1 API also failed', [
                                'status' => $v1Status,
                                'response' => substr($v1Body, 0, 500),
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::warning('Twitter DM fetch: v1.1 fallback error', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    // Both failed - return empty
                    Log::error('Twitter DM fetch: Both v2 and v1.1 failed. App needs to be in a Project for v2, or use OAuth 1.0a for v1.1', [
                        'account_id' => $account->id,
                        'help_url' => 'https://developer.twitter.com/en/docs/projects/overview',
                    ]);
                    return [];
                }
                
                Log::error('Twitter DM fetch failed', [
                    'status' => $status,
                    'response' => $body,
                    'error_data' => $errorData,
                    'account_id' => $account->id,
                ]);
                return [];
            }

            $data = $response->json();
            
            // v2 API returns {data: [...]}
            $events = $data['data'] ?? [];
            
            Log::info('Twitter DM fetch: v2 API success', [
                'events_count' => count($events),
                'account_id' => $account->id,
            ]);

            return $events;
        } catch (Exception $e) {
            Log::error('Twitter DM fetch error: ' . $e->getMessage(), [
                'account_id' => $account->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Process and store Twitter messages
     */
    public function processMessages(Organization $organization, array $messages, SocialAccount $account = null)
    {
        $processed = 0;

        // Get Twitter account if not provided
        if (!$account) {
            $account = \App\Models\SocialAccount::where('organization_id', $organization->id)
                ->where('platform', 'twitter')
                ->where('is_active', true)
                ->first();
        }

        if (!$account) {
            Log::error('Twitter processMessages: No active Twitter account found', [
                'organization_id' => $organization->id,
            ]);
            return 0;
        }

        $accessToken = $account->access_token;

        foreach ($messages as $message) {
            try {
                // Twitter DM structure - v1.1 API returns messages directly
                // v1.1 format: {id, text, sender_id, recipient_id, created_at, ...}
                // v2 format: {dm_event: {id, text, sender_id, recipient_id, created_timestamp, ...}}
                $dmEvent = $message['dm_event'] ?? $message;
                
                // v1.1 uses 'sender_id' and 'recipient_id' directly
                // v2 also uses 'sender_id' and 'recipient_id'
                $senderId = $dmEvent['sender_id'] ?? $message['sender_id'] ?? $message['sender']['id'] ?? null;
                $recipientId = $dmEvent['recipient_id'] ?? $message['recipient_id'] ?? $message['recipient']['id'] ?? null;
                
                if (!$senderId) {
                    Log::warning('Twitter processMessages: Message missing sender_id', [
                        'message' => $message,
                    ]);
                    continue;
                }

                // Get sender info
                try {
                    $senderResponse = Http::timeout(30)->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                    ])->get("{$this->baseUrl}/users/{$senderId}", [
                        'user.fields' => 'id,name,username,profile_image_url',
                    ]);

                    if ($senderResponse->successful()) {
                        $senderData = $senderResponse->json()['data'] ?? null;
                        $senderName = $senderData['name'] ?? 'Twitter User';
                        $senderUsername = $senderData['username'] ?? 'twitter_user';
                    } else {
                        Log::warning('Twitter processMessages: Failed to fetch sender info', [
                            'sender_id' => $senderId,
                            'response' => $senderResponse->body(),
                        ]);
                        $senderName = 'Twitter User';
                        $senderUsername = 'twitter_user';
                    }
                } catch (Exception $e) {
                    Log::warning('Twitter processMessages: Error fetching sender info', [
                        'sender_id' => $senderId,
                        'error' => $e->getMessage(),
                    ]);
                    $senderName = 'Twitter User';
                    $senderUsername = 'twitter_user';
                }

                // Find or create contact
                $contact = Contact::firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'phone' => 'tw_' . $senderId,
                    ],
                    [
                        'first_name' => $senderName,
                        'last_name' => '',
                        'email' => null,
                        'created_by' => 0,
                    ]
                );

                // Check if message already exists
                $existingChat = Chat::where('platform', 'twitter')
                    ->where('platform_message_id', $dmEvent['id'])
                    ->where('contact_id', $contact->id)
                    ->first();

                if ($existingChat) {
                    continue;
                }

                // Extract message text - handle both v1.1 and v2 structures
                $messageText = $dmEvent['text'] ?? $message['text'] ?? $dmEvent['message_create']['message_data']['text'] ?? '';
                $dmEventId = $dmEvent['id'] ?? $message['id'] ?? null;
                
                if (!$dmEventId) {
                    Log::warning('Twitter processMessages: Message missing ID', [
                        'dm_event' => $dmEvent,
                        'message' => $message,
                    ]);
                    continue;
                }

                // Parse created_at - v1.1 uses 'created_at' string, v2 uses 'created_timestamp' (milliseconds)
                $createdAt = now();
                if (isset($dmEvent['created_timestamp'])) {
                    $createdAt = \Carbon\Carbon::createFromTimestamp($dmEvent['created_timestamp'] / 1000);
                } elseif (isset($message['created_at'])) {
                    $createdAt = \Carbon\Carbon::parse($message['created_at']);
                } elseif (isset($dmEvent['created_at'])) {
                    $createdAt = \Carbon\Carbon::parse($dmEvent['created_at']);
                }

                // Create chat
                $chat = Chat::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'organization_id' => $organization->id,
                    'contact_id' => $contact->id,
                    'platform' => 'twitter',
                    'platform_message_id' => (string)$dmEventId,
                    'type' => 'inbound',
                    'metadata' => json_encode([
                        'type' => 'text',
                        'text' => [
                            'body' => $messageText,
                        ],
                        'sender_id' => $senderId,
                        'sender_username' => $senderUsername,
                    ]),
                    'platform_data' => json_encode([
                        'dm_event' => $dmEvent,
                        'raw_message' => $message,
                    ]),
                    'status' => 'delivered',
                    'created_at' => $createdAt,
                ]);

                // Create chat log
                ChatLog::create([
                    'contact_id' => $contact->id,
                    'entity_type' => 'chat',
                    'entity_id' => $chat->id,
                    'created_at' => $chat->created_at,
                ]);

                $processed++;
            } catch (Exception $e) {
                Log::error('Error processing Twitter message: ' . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Send message via Twitter Direct Messages
     */
    public function sendMessage(SocialAccount $account, $recipientId, $message)
    {
        try {
            $accessToken = $account->access_token;

            // Use v2 API for sending DMs
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/dm_events", [
                'event' => [
                    'type' => 'MessageCreate',
                    'message_create' => [
                        'target' => [
                            'recipient_id' => $recipientId,
                        ],
                        'message_data' => [
                            'text' => $message,
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to send message: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Twitter send message error: ' . $e->getMessage());
            throw $e;
        }
    }
}




