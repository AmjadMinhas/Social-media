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

    /**
     * Fetch messages from Twitter Direct Messages
     */
    public function fetchMessages(SocialAccount $account, $since = null)
    {
        try {
            $accessToken = $account->access_token;

            // Get direct message events
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->baseUrl}/dm/events", [
                'max_results' => 50,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to fetch DMs: ' . $response->body());
            }

            $data = $response->json();
            return $data['data'] ?? [];
        } catch (Exception $e) {
            Log::error('Twitter DM fetch error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Process and store Twitter messages
     */
    public function processMessages(Organization $organization, array $messages)
    {
        $processed = 0;

        foreach ($messages as $message) {
            try {
                // Twitter DM structure
                $dmEvent = $message['dm_event'] ?? $message;
                $senderId = $dmEvent['sender_id'] ?? null;
                $recipientId = $dmEvent['recipient_id'] ?? null;
                
                if (!$senderId) {
                    continue;
                }

                // Get sender info
                $senderResponse = Http::withHeaders([
                    'Authorization' => "Bearer {$account->access_token}",
                ])->get("{$this->baseUrl}/users/{$senderId}", [
                    'user.fields' => 'name,username',
                ]);

                $senderData = $senderResponse->json()['data'] ?? null;
                $senderName = $senderData['name'] ?? 'Twitter User';
                $senderUsername = $senderData['username'] ?? 'twitter_user';

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

                // Extract message text
                $messageText = $dmEvent['text'] ?? $dmEvent['message_create']['message_data']['text'] ?? '';

                // Create chat
                $chat = Chat::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'organization_id' => $organization->id,
                    'contact_id' => $contact->id,
                    'platform' => 'twitter',
                    'platform_message_id' => $dmEvent['id'],
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
                    ]),
                    'status' => 'delivered',
                    'created_at' => isset($dmEvent['created_timestamp']) 
                        ? \Carbon\Carbon::createFromTimestamp($dmEvent['created_timestamp'] / 1000) 
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

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/dm/events", [
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




