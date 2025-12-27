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

            Log::info('Facebook Messenger: Fetching messages', [
                'page_id' => $pageId,
                'account_id' => $account->id,
                'has_access_token' => !empty($accessToken),
                'platform_data' => $account->platform_data,
                'platform_user_id' => $account->platform_user_id
            ]);

            if (empty($accessToken)) {
                Log::error('Facebook Messenger: No access token found');
                throw new Exception('No access token found for Facebook account');
            }

            // First, verify the token has the required permissions
            $tokenInfoResponse = Http::get("{$this->baseUrl}/debug_token", [
                'input_token' => $accessToken,
                'access_token' => config('socialmedia.facebook.app_id') . '|' . config('socialmedia.facebook.app_secret'),
            ]);

            if ($tokenInfoResponse->successful()) {
                $tokenInfo = $tokenInfoResponse->json();
                $scopes = $tokenInfo['data']['scopes'] ?? [];
                Log::info('Facebook Messenger: Token permissions', [
                    'scopes' => $scopes,
                    'has_pages_messaging' => in_array('pages_messaging', $scopes),
                    'has_pages_read_mailboxes' => in_array('pages_read_mailboxes', $scopes),
                ]);

                if (!in_array('pages_messaging', $scopes)) {
                    Log::error('Facebook Messenger: Missing required permissions', [
                        'available_scopes' => $scopes,
                        'required_scope' => 'pages_messaging'
                    ]);
                    throw new Exception('Facebook page access token is missing required permission (pages_messaging). Please reconnect the Facebook page.');
                }
            }

            // Get conversations - use page access token
            $conversationsResponse = Http::get("{$this->baseUrl}/{$pageId}/conversations", [
                'access_token' => $accessToken,
                'fields' => 'id,updated_time,message_count,participants',
                'limit' => 50,
            ]);

            if ($conversationsResponse->failed()) {
                $errorBody = $conversationsResponse->body();
                $errorJson = json_decode($errorBody, true);
                $errorMessage = $errorJson['error']['message'] ?? 'Unknown error';
                $errorCode = $errorJson['error']['code'] ?? null;
                
                Log::error('Facebook Messenger: Failed to fetch conversations', [
                    'page_id' => $pageId,
                    'status' => $conversationsResponse->status(),
                    'error' => $errorBody,
                    'error_code' => $errorCode,
                    'error_type' => $errorJson['error']['type'] ?? null,
                    'error_message' => $errorMessage,
                    'error_subcode' => $errorJson['error']['error_subcode'] ?? null
                ]);
                
                // If it's a permission error, throw with helpful message
                if ($errorCode == 200 && str_contains($errorMessage, 'pages_messaging')) {
                    throw new Exception(
                        'Facebook Messenger permission error: The page access token is missing the "pages_messaging" permission. ' .
                        'Please disconnect and reconnect your Facebook page to grant the required permissions. ' .
                        'Also ensure you have Admin, Editor, or Moderator role on the Facebook page.'
                    );
                }
                
                // Don't throw for other errors - return empty array so sync can continue
                return [];
            }

            $conversations = $conversationsResponse->json()['data'] ?? [];
            $messages = [];

            Log::info('Facebook Messenger: Conversations found', [
                'conversations_count' => count($conversations)
            ]);

            foreach ($conversations as $conversation) {
                $conversationId = $conversation['id'];
                
                // Get conversation participants to get the PSID (Page-Scoped ID)
                $participantsResponse = Http::get("{$this->baseUrl}/{$conversationId}", [
                    'access_token' => $accessToken,
                    'fields' => 'participants',
                ]);
                
                $participants = [];
                if ($participantsResponse->successful()) {
                    $participantsData = $participantsResponse->json();
                    $participants = $participantsData['participants']['data'] ?? [];
                }
                
                // Get messages in conversation - need to get participants first to identify sender
                $messagesResponse = Http::get("{$this->baseUrl}/{$conversationId}/messages", [
                    'access_token' => $accessToken,
                    'fields' => 'id,from,to,message,created_time,attachments',
                    'limit' => 25,
                ]);

                if ($messagesResponse->successful()) {
                    $conversationMessages = $messagesResponse->json()['data'] ?? [];
                    
                    Log::info('Facebook Messenger: Messages in conversation', [
                        'conversation_id' => $conversationId,
                        'messages_count' => count($conversationMessages),
                        'first_message_sample' => !empty($conversationMessages) ? [
                            'id' => $conversationMessages[0]['id'] ?? null,
                            'has_from' => isset($conversationMessages[0]['from']),
                            'has_message' => isset($conversationMessages[0]['message'])
                        ] : null
                    ]);
                    
                    foreach ($conversationMessages as $message) {
                        // Only include messages from users (not from the page itself)
                        $fromId = $message['from']['id'] ?? null;
                        if ($fromId && $fromId !== $pageId) {
                            // Find the PSID from participants (the user who is not the page)
                            $psid = null;
                            foreach ($participants as $participant) {
                                $participantId = $participant['id'] ?? null;
                                if ($participantId && $participantId !== $pageId) {
                                    $psid = $participantId;
                                    break;
                                }
                            }
                            
                            // If PSID not found in participants, use the from ID
                            if (!$psid) {
                                $psid = $fromId;
                            }
                            
                            $messages[] = [
                                'conversation_id' => $conversationId,
                                'message' => $message,
                                'psid' => $psid, // Store the PSID for sending messages
                            ];
                        }
                    }
                } else {
                    $errorBody = $messagesResponse->body();
                    $errorJson = json_decode($errorBody, true);
                    
                    Log::warning('Facebook Messenger: Failed to fetch messages for conversation', [
                        'conversation_id' => $conversationId,
                        'status' => $messagesResponse->status(),
                        'error' => $errorBody,
                        'error_code' => $errorJson['error']['code'] ?? null,
                        'error_type' => $errorJson['error']['type'] ?? null,
                        'error_message' => $errorJson['error']['message'] ?? null
                    ]);
                }
            }

            Log::info('Facebook Messenger: Total messages fetched', [
                'total_messages' => count($messages)
            ]);

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
                
                // Get sender info - use PSID if available, otherwise use from ID
                $psid = $item['psid'] ?? $message['from']['id'] ?? null;
                $senderId = $message['from']['id'] ?? null;
                $senderName = $message['from']['name'] ?? 'Facebook User';
                $messageText = $message['message'] ?? '';
                
                // Skip if no sender ID or empty message
                if (!$psid || empty($messageText)) {
                    Log::debug('Facebook Messenger: Skipping message - no PSID or empty', [
                        'has_psid' => !empty($psid),
                        'has_sender_id' => !empty($senderId),
                        'has_message' => !empty($messageText),
                        'message_id' => $message['id'] ?? null
                    ]);
                    continue;
                }
                
                Log::debug('Facebook Messenger: Processing message', [
                    'message_id' => $message['id'] ?? null,
                    'psid' => $psid,
                    'sender_id' => $senderId,
                    'sender_name' => $senderName,
                    'message_preview' => substr($messageText, 0, 50)
                ]);

                // Find or create contact - use PSID for phone field
                $contact = Contact::firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'phone' => 'fb_' . $psid, // Use PSID as identifier for sending messages
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
                    Log::debug('Facebook Messenger: Message already exists, skipping', [
                        'message_id' => $message['id'] ?? null,
                        'existing_chat_id' => $existingChat->id
                    ]);
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
                        'psid' => $psid, // Store PSID for sending messages
                        'sender_id' => $senderId,
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
                
                Log::debug('Facebook Messenger: Message saved successfully', [
                    'message_id' => $message['id'] ?? null,
                    'chat_id' => $chat->id,
                    'contact_id' => $contact->id
                ]);
            } catch (Exception $e) {
                Log::error('Facebook Messenger: Error processing message', [
                    'message_id' => $message['id'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
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












