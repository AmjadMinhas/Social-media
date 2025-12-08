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

            // Get conversations (threads)
            $threadsResponse = Http::get("{$this->baseUrl}/{$igAccountId}/conversations", [
                'access_token' => $accessToken,
                'fields' => 'id,thread_id,updated_time',
                'limit' => 50,
            ]);

            if ($threadsResponse->failed()) {
                throw new Exception('Failed to fetch conversations: ' . $threadsResponse->body());
            }

            $threads = $threadsResponse->json()['data'] ?? [];
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
                    
                    foreach ($threadMessages as $message) {
                        $messages[] = [
                            'thread_id' => $threadId,
                            'message' => $message,
                        ];
                    }
                }
            }

            return $messages;
        } catch (Exception $e) {
            Log::error('Instagram Messenger fetch error: ' . $e->getMessage());
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




