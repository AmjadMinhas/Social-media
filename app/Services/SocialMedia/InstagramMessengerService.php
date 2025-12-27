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
 * Instagram Messenger Service
 * Handles fetching and sending messages via Instagram Direct Messages
 */
class InstagramMessengerService
{
    protected $apiVersion;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiVersion = config('socialmedia.instagram.graph_api_version');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Fetch messages from Instagram Direct Messages
     */
    public function fetchMessages(SocialAccount $account, $since = null)
    {
        try {
            $igAccountId = $account->platform_user_id;
            $accessToken = $account->access_token;

            Log::info('Instagram Messenger: Fetching messages', [
                'ig_account_id' => $igAccountId,
                'has_access_token' => !empty($accessToken),
            ]);

            // Instagram DMs API endpoint - try using the page's inbox instead
            // Note: Instagram DMs are accessed through the linked Facebook Page's conversations
            // We need to get the Facebook Page ID first from the account's platform_data
            
            $pageId = $account->platform_data['page_id'] ?? null;
            
            if (!$pageId) {
                Log::warning('Instagram Messenger: No Facebook Page ID found in account data', [
                    'platform_data' => $account->platform_data,
                ]);
                // Try to get it from the Instagram account's linked page
                $igAccountResponse = Http::get("{$this->baseUrl}/{$igAccountId}", [
                    'fields' => 'connected_facebook_page',
                    'access_token' => $accessToken,
                ]);
                
                if ($igAccountResponse->successful()) {
                    $igAccountData = $igAccountResponse->json();
                    $pageId = $igAccountData['connected_facebook_page']['id'] ?? null;
                }
            }
            
            if (!$pageId) {
                Log::error('Instagram Messenger: Cannot find Facebook Page ID for Instagram account');
                return [];
            }
            
            // Use the Facebook Page's conversations endpoint for Instagram DMs
            $threadsResponse = Http::get("{$this->baseUrl}/{$pageId}/conversations", [
                'access_token' => $accessToken,
                'fields' => 'id,updated_time,message_count,participants,can_reply',
                'platform' => 'instagram',  // Filter for Instagram conversations only
                'limit' => 50,
            ]);

            if ($threadsResponse->failed()) {
                $errorBody = $threadsResponse->body();
                $errorData = json_decode($errorBody, true);
                $errorCode = $errorData['error']['code'] ?? null;
                $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
                
                // Instagram Graph API doesn't support fetching DMs for most apps
                // Error code 3 means "Application does not have the capability to make this API call"
                if ($errorCode == 3) {
                    Log::warning('Instagram Messenger: API capability not available', [
                        'message' => 'Instagram Direct Messages API requires special permissions/capabilities that are not available for this app. Instagram DMs can only be accessed through Instagram Messaging API which requires separate setup.',
                        'error_code' => $errorCode,
                    ]);
                    return []; // Return empty array instead of throwing error
                }
                
                Log::error('Instagram Messenger: Failed to fetch conversations', [
                    'status' => $threadsResponse->status(),
                    'response' => $errorBody,
                    'ig_account_id' => $igAccountId,
                    'error_code' => $errorCode,
                ]);
                throw new Exception('Failed to fetch conversations: ' . $errorBody);
            }

            $threads = $threadsResponse->json()['data'] ?? [];
            Log::info('Instagram Messenger: Conversations fetched', [
                'threads_count' => count($threads),
            ]);

            $messages = [];

            foreach ($threads as $thread) {
                $threadId = $thread['id'];
                
                // Get messages in thread
                $messagesResponse = Http::get("{$this->baseUrl}/{$threadId}", [
                    'access_token' => $accessToken,
                    'fields' => 'messages{id,from,text,timestamp,media_url}',
                ]);

                if ($messagesResponse->successful()) {
                    $threadMessages = $messagesResponse->json()['messages']['data'] ?? [];
                    
                    Log::info('Instagram Messenger: Messages in thread', [
                        'thread_id' => $threadId,
                        'messages_count' => count($threadMessages),
                    ]);
                    
                    foreach ($threadMessages as $message) {
                        $messages[] = [
                            'thread_id' => $threadId,
                            'message' => $message,
                        ];
                    }
                } else {
                    Log::warning('Instagram Messenger: Failed to get messages for thread', [
                        'thread_id' => $threadId,
                        'status' => $messagesResponse->status(),
                        'response' => $messagesResponse->body(),
                    ]);
                }
            }

            Log::info('Instagram Messenger: Total messages fetched', [
                'total_messages' => count($messages),
            ]);

            return $messages;
        } catch (Exception $e) {
            Log::error('Instagram Messenger fetch error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Process and store Instagram messages
     */
    public function processMessages(Organization $organization, array $messages)
    {
        $processed = 0;

        foreach ($messages as $item) {
            try {
                $message = $item['message'];
                $threadId = $item['thread_id'];
                
                $senderId = $message['from']['id'] ?? null;
                $senderUsername = $message['from']['username'] ?? 'Instagram User';
                
                if (!$senderId) {
                    continue;
                }

                // Find or create contact
                $contact = Contact::firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'phone' => 'ig_' . $senderId,
                    ],
                    [
                        'first_name' => $senderUsername,
                        'last_name' => '',
                        'email' => null,
                        'created_by' => 0,
                    ]
                );

                // Check if message already exists
                $existingChat = Chat::where('platform', 'instagram')
                    ->where('platform_message_id', $message['id'])
                    ->where('contact_id', $contact->id)
                    ->first();

                if ($existingChat) {
                    continue;
                }

                // Create chat
                $chat = Chat::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'organization_id' => $organization->id,
                    'contact_id' => $contact->id,
                    'platform' => 'instagram',
                    'platform_message_id' => $message['id'],
                    'platform_thread_id' => $threadId,
                    'type' => 'inbound',
                    'metadata' => json_encode([
                        'type' => 'text',
                        'text' => [
                            'body' => $message['text'] ?? '',
                        ],
                        'from' => $message['from'],
                    ]),
                    'platform_data' => json_encode([
                        'thread_id' => $threadId,
                        'media_url' => $message['media_url'] ?? null,
                    ]),
                    'status' => 'delivered',
                    'created_at' => isset($message['timestamp']) 
                        ? \Carbon\Carbon::createFromTimestamp($message['timestamp']) 
                        : now(),
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
                Log::error('Error processing Instagram message: ' . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Send message via Instagram Direct Messages
     */
    public function sendMessage(SocialAccount $account, $recipientId, $message)
    {
        try {
            $igAccountId = $account->platform_user_id;
            $accessToken = $account->access_token;

            $response = Http::post("{$this->baseUrl}/{$igAccountId}/messages", [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
                'access_token' => $accessToken,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to send message: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Instagram send message error: ' . $e->getMessage());
            throw $e;
        }
    }
}















