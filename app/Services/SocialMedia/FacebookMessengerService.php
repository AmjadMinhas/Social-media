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
 * Facebook Messenger Service
 * Handles fetching and sending messages via Facebook Messenger
 */
class FacebookMessengerService
{
    protected $apiVersion;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiVersion = config('socialmedia.facebook.graph_api_version');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Fetch messages from Facebook Messenger
     */
    public function fetchMessages(SocialAccount $account, $since = null)
    {
        try {
            $pageId = $account->platform_data['page_id'] ?? $account->platform_user_id;
            $accessToken = $account->access_token;

            // Get conversations
            $conversationsResponse = Http::get("{$this->baseUrl}/{$pageId}/conversations", [
                'access_token' => $accessToken,
                'fields' => 'id,updated_time,message_count',
                'limit' => 50,
            ]);

            if ($conversationsResponse->failed()) {
                throw new Exception('Failed to fetch conversations: ' . $conversationsResponse->body());
            }

            $conversations = $conversationsResponse->json()['data'] ?? [];
            $messages = [];

            foreach ($conversations as $conversation) {
                $conversationId = $conversation['id'];
                
                // Get messages in conversation
                $messagesResponse = Http::get("{$this->baseUrl}/{$conversationId}/messages", [
                    'access_token' => $accessToken,
                    'fields' => 'id,from,to,message,created_time,attachments',
                    'limit' => 25,
                ]);

                if ($messagesResponse->successful()) {
                    $conversationMessages = $messagesResponse->json()['data'] ?? [];
                    
                    foreach ($conversationMessages as $message) {
                        $messages[] = [
                            'conversation_id' => $conversationId,
                            'message' => $message,
                        ];
                    }
                }
            }

            return $messages;
        } catch (Exception $e) {
            Log::error('Facebook Messenger fetch error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Process and store Facebook messages
     */
    public function processMessages(Organization $organization, array $messages)
    {
        $processed = 0;

        foreach ($messages as $item) {
            try {
                $message = $item['message'];
                $conversationId = $item['conversation_id'];
                
                // Get sender info
                $senderId = $message['from']['id'] ?? null;
                $senderName = $message['from']['name'] ?? 'Facebook User';
                
                if (!$senderId) {
                    continue;
                }

                // Find or create contact
                $contact = Contact::firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'phone' => 'fb_' . $senderId, // Use Facebook ID as identifier
                    ],
                    [
                        'first_name' => explode(' ', $senderName)[0] ?? 'Facebook',
                        'last_name' => implode(' ', array_slice(explode(' ', $senderName), 1)) ?? 'User',
                        'email' => null,
                        'created_by' => 0,
                    ]
                );

                // Check if message already exists
                $existingChat = Chat::where('platform', 'facebook')
                    ->where('platform_message_id', $message['id'])
                    ->where('contact_id', $contact->id)
                    ->first();

                if ($existingChat) {
                    continue; // Skip if already processed
                }

                // Create chat
                $chat = Chat::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'organization_id' => $organization->id,
                    'contact_id' => $contact->id,
                    'platform' => 'facebook',
                    'platform_message_id' => $message['id'],
                    'platform_thread_id' => $conversationId,
                    'type' => 'inbound',
                    'metadata' => json_encode([
                        'type' => 'text',
                        'text' => [
                            'body' => $message['message'] ?? '',
                        ],
                        'from' => $message['from'],
                        'to' => $message['to'] ?? [],
                    ]),
                    'platform_data' => json_encode([
                        'conversation_id' => $conversationId,
                        'attachments' => $message['attachments']['data'] ?? [],
                    ]),
                    'status' => 'delivered',
                    'created_at' => isset($message['created_time']) 
                        ? \Carbon\Carbon::parse($message['created_time']) 
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
                Log::error('Error processing Facebook message: ' . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Send message via Facebook Messenger
     */
    public function sendMessage(SocialAccount $account, $recipientId, $message)
    {
        try {
            $pageId = $account->platform_data['page_id'] ?? $account->platform_user_id;
            $accessToken = $account->access_token;

            $response = Http::post("{$this->baseUrl}/me/messages", [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
                'access_token' => $accessToken,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to send message: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Facebook send message error: ' . $e->getMessage());
            throw $e;
        }
    }
}






