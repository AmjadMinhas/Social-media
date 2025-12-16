<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SocialAccount;
use App\Services\UnifiedSocialInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * Social Media Webhook Controller
 * Handles webhooks from Facebook, Instagram, Twitter, etc.
 */
class SocialMediaWebhookController extends Controller
{
    protected $inboxService;

    public function __construct()
    {
        $this->inboxService = new UnifiedSocialInboxService();
    }

    /**
     * Facebook/Instagram Webhook Handler
     */
    public function handleFacebookWebhook(Request $request, $organizationId)
    {
        try {
            $organization = Organization::find($organizationId);
            
            if (!$organization) {
                return Response::json(['error' => 'Organization not found'], 404);
            }

            // Verify webhook (Facebook requires this)
            if ($request->method() === 'GET') {
                $mode = $request->input('hub_mode');
                $token = $request->input('hub_verify_token');
                $challenge = $request->input('hub_challenge');

                // Verify token (you should store this securely)
                $verifyToken = config('socialmedia.facebook.webhook_verify_token');
                
                if ($mode === 'subscribe' && $token === $verifyToken) {
                    return Response::make($challenge, 200);
                }

                return Response::json(['error' => 'Verification failed'], 403);
            }

            // Handle POST (actual webhook data)
            $data = $request->all();
            
            // Process Facebook Messenger messages
            if (isset($data['entry'])) {
                foreach ($data['entry'] as $entry) {
                    if (isset($entry['messaging'])) {
                        foreach ($entry['messaging'] as $messaging) {
                            $this->processFacebookMessage($organization, $messaging);
                        }
                    }
                }
            }

            return Response::json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Facebook webhook error: ' . $e->getMessage());
            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Process Facebook Messenger message
     */
    protected function processFacebookMessage(Organization $organization, array $messaging)
    {
        try {
            $senderId = $messaging['sender']['id'] ?? null;
            $message = $messaging['message'] ?? null;

            if (!$senderId || !$message) {
                return;
            }

            // Get or create contact
            $contact = \App\Models\Contact::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'phone' => 'fb_' . $senderId,
                ],
                [
                    'first_name' => 'Facebook',
                    'last_name' => 'User',
                    'email' => null,
                    'created_by' => 0,
                ]
            );

            // Check if message already exists
            $messageId = $message['mid'] ?? null;
            if ($messageId) {
                $existing = \App\Models\Chat::where('platform', 'facebook')
                    ->where('platform_message_id', $messageId)
                    ->first();

                if ($existing) {
                    return; // Already processed
                }
            }

            // Create chat
            $chat = \App\Models\Chat::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'organization_id' => $organization->id,
                'contact_id' => $contact->id,
                'platform' => 'facebook',
                'platform_message_id' => $messageId,
                'type' => 'inbound',
                'metadata' => json_encode([
                    'type' => 'text',
                    'text' => ['body' => $message['text'] ?? ''],
                ]),
                'status' => 'delivered',
                'created_at' => isset($messaging['timestamp']) 
                    ? \Carbon\Carbon::createFromTimestamp($messaging['timestamp'] / 1000) 
                    : now(),
            ]);

            // Create chat log
            \App\Models\ChatLog::create([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => $chat->created_at,
            ]);

            // Trigger event
            event(new \App\Events\NewChatEvent(
                [['type' => 'chat', 'value' => $chat]],
                $organization->id
            ));
        } catch (\Exception $e) {
            Log::error('Error processing Facebook message: ' . $e->getMessage());
        }
    }

    /**
     * Twitter Webhook Handler
     */
    public function handleTwitterWebhook(Request $request, $organizationId)
    {
        try {
            $organization = Organization::find($organizationId);
            
            if (!$organization) {
                return Response::json(['error' => 'Organization not found'], 404);
            }

            // Twitter webhook verification
            if ($request->method() === 'GET') {
                $crcToken = $request->input('crc_token');
                
                if ($crcToken) {
                    // Verify CRC token (Twitter requires this)
                    $consumerSecret = config('socialmedia.twitter.client_secret');
                    $responseToken = base64_encode(hash_hmac('sha256', $crcToken, $consumerSecret, true));
                    
                    return Response::json([
                        'response_token' => 'sha256=' . $responseToken,
                    ], 200);
                }
            }

            // Handle POST (actual webhook data)
            $data = $request->all();
            
            // Process Twitter Direct Messages
            if (isset($data['direct_message_events'])) {
                foreach ($data['direct_message_events'] as $dmEvent) {
                    $this->processTwitterMessage($organization, $dmEvent);
                }
            }

            return Response::json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Twitter webhook error: ' . $e->getMessage());
            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Process Twitter Direct Message
     */
    protected function processTwitterMessage(Organization $organization, array $dmEvent)
    {
        try {
            $senderId = $dmEvent['message_create']['sender_id'] ?? null;
            $messageText = $dmEvent['message_create']['message_data']['text'] ?? '';

            if (!$senderId) {
                return;
            }

            // Get or create contact
            $contact = \App\Models\Contact::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'phone' => 'tw_' . $senderId,
                ],
                [
                    'first_name' => 'Twitter',
                    'last_name' => 'User',
                    'email' => null,
                    'created_by' => 0,
                ]
            );

            // Check if message already exists
            $messageId = $dmEvent['id'] ?? null;
            if ($messageId) {
                $existing = \App\Models\Chat::where('platform', 'twitter')
                    ->where('platform_message_id', $messageId)
                    ->first();

                if ($existing) {
                    return;
                }
            }

            // Create chat
            $chat = \App\Models\Chat::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'organization_id' => $organization->id,
                'contact_id' => $contact->id,
                'platform' => 'twitter',
                'platform_message_id' => $messageId,
                'type' => 'inbound',
                'metadata' => json_encode([
                    'type' => 'text',
                    'text' => ['body' => $messageText],
                ]),
                'status' => 'delivered',
                'created_at' => now(),
            ]);

            // Create chat log
            \App\Models\ChatLog::create([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => $chat->created_at,
            ]);

            // Trigger event
            event(new \App\Events\NewChatEvent(
                [['type' => 'chat', 'value' => $chat]],
                $organization->id
            ));
        } catch (\Exception $e) {
            Log::error('Error processing Twitter message: ' . $e->getMessage());
        }
    }
}






