<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Services\SocialMedia\FacebookMessengerService;
use App\Services\SocialMedia\InstagramMessengerService;
use App\Services\SocialMedia\TwitterMessengerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Unified Social Inbox Service
 * Combines messages from all social media platforms into a single inbox
 */
class UnifiedSocialInboxService
{
    protected $facebookService;
    protected $instagramService;
    protected $twitterService;

    public function __construct()
    {
        $this->facebookService = new FacebookMessengerService();
        $this->instagramService = new InstagramMessengerService();
        $this->twitterService = new TwitterMessengerService();
    }

    /**
     * Sync messages from all connected platforms
     */
    public function syncAllPlatforms($organizationId)
    {
        $organization = Organization::find($organizationId);
        
        if (!$organization) {
            return ['success' => false, 'message' => 'Organization not found'];
        }

        $results = [
            'facebook' => 0,
            'instagram' => 0,
            'twitter' => 0,
            'total' => 0,
        ];

        // Sync Facebook Messenger
        $facebookAccount = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', 'facebook')
            ->where('is_active', true)
            ->first();

        if ($facebookAccount) {
            try {
                Log::info('UnifiedSocialInbox: Starting Facebook sync', [
                    'account_id' => $facebookAccount->id,
                    'page_id' => $facebookAccount->platform_data['page_id'] ?? null,
                    'organization_id' => $organizationId
                ]);
                
                $messages = $this->facebookService->fetchMessages($facebookAccount);
                
                Log::info('UnifiedSocialInbox: Facebook messages fetched', [
                    'messages_count' => count($messages)
                ]);
                
                $processed = $this->facebookService->processMessages($organization, $messages);
                
                Log::info('UnifiedSocialInbox: Facebook messages processed', [
                    'processed_count' => $processed
                ]);
                
                $results['facebook'] = $processed;
                $results['total'] += $processed;
            } catch (\Exception $e) {
                Log::error('Facebook sync error: ' . $e->getMessage(), [
                    'account_id' => $facebookAccount->id ?? null,
                    'organization_id' => $organizationId,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::info('UnifiedSocialInbox: No active Facebook account found', [
                'organization_id' => $organizationId,
                'total_facebook_accounts' => SocialAccount::where('organization_id', $organizationId)
                    ->where('platform', 'facebook')
                    ->count(),
                'active_facebook_accounts' => SocialAccount::where('organization_id', $organizationId)
                    ->where('platform', 'facebook')
                    ->where('is_active', true)
                    ->count()
            ]);
        }

        // Sync Instagram Direct Messages
        $instagramAccount = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', 'instagram')
            ->where('is_active', true)
            ->first();

        if ($instagramAccount) {
            try {
                $messages = $this->instagramService->fetchMessages($instagramAccount);
                $processed = $this->instagramService->processMessages($organization, $messages);
                $results['instagram'] = $processed;
                $results['total'] += $processed;
            } catch (\Exception $e) {
                Log::error('Instagram sync error: ' . $e->getMessage());
            }
        }

        // Sync Twitter Direct Messages
        $twitterAccount = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', 'twitter')
            ->where('is_active', true)
            ->first();

        if ($twitterAccount) {
            try {
                $messages = $this->twitterService->fetchMessages($twitterAccount);
                $processed = $this->twitterService->processMessages($organization, $messages);
                $results['twitter'] = $processed;
                $results['total'] += $processed;
            } catch (\Exception $e) {
                Log::error('Twitter sync error: ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'results' => $results,
            'message' => "Synced {$results['total']} messages from all platforms",
        ];
    }

    /**
     * Get unified inbox messages (all platforms combined)
     */
    public function getUnifiedInbox($organizationId, $filters = [])
    {
        $query = Chat::where('chats.organization_id', $organizationId)
            ->whereNull('chats.deleted_at')
            ->with(['contact', 'media', 'user'])
            ->orderBy('chats.created_at', 'desc');

        // Filter by platform (only if column exists)
        if (isset($filters['platform']) && $filters['platform']) {
            if (Schema::hasColumn('chats', 'platform')) {
                $query->where('chats.platform', $filters['platform']);
            }
        }

        // Filter by type (inbound/outbound)
        if (isset($filters['type']) && $filters['type']) {
            $query->where('chats.type', $filters['type']);
        }

        // Filter by contact
        if (isset($filters['contact_id']) && $filters['contact_id']) {
            $query->where('chats.contact_id', $filters['contact_id']);
        }

        // Search
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->whereHas('contact', function($contactQuery) use ($search) {
                    $contactQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orWhere('chats.metadata', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get contacts with messages from all platforms
     */
    public function getUnifiedContacts($organizationId, $filters = [])
    {
        try {
            $query = Contact::where('contacts.organization_id', $organizationId)
                ->whereNull('contacts.deleted_at')
                ->whereHas('chats', function($chatQuery) use ($organizationId) {
                    $chatQuery->where('chats.organization_id', $organizationId)
                        ->whereNull('chats.deleted_at');
                }, '>', 0)
                ->with(['lastChat', 'lastInboundChat', 'chats' => function($chatQuery) {
                    $chatQuery->whereNull('chats.deleted_at')
                        ->latest()
                        ->limit(10);
                }])
                ->withCount(['chats' => function($chatQuery) use ($organizationId) {
                    $chatQuery->where('chats.organization_id', $organizationId)
                        ->whereNull('chats.deleted_at');
                }]);

        // Filter by platform (only if column exists)
        if (isset($filters['platform']) && $filters['platform']) {
            if (Schema::hasColumn('chats', 'platform')) {
                $query->whereHas('chats', function($chatQuery) use ($filters) {
                    $chatQuery->where('platform', $filters['platform']);
                });
            }
        }

        // Search
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

            return $query->orderBy('latest_chat_created_at', 'desc')
                ->paginate($filters['per_page'] ?? 20);
        } catch (\Exception $e) {
            Log::error('Error in getUnifiedContacts: ' . $e->getMessage(), [
                'organization_id' => $organizationId,
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            // Return empty paginated result on error
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20, 1);
        }
    }

    /**
     * Send message to any platform
     */
    public function sendMessage($organizationId, $platform, $contactId, $message, $userId = null)
    {
        $contact = Contact::find($contactId);
        
        if (!$contact || $contact->organization_id != $organizationId) {
            return ['success' => false, 'message' => 'Contact not found'];
        }

        $account = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();

        if (!$account) {
            return ['success' => false, 'message' => "No active {$platform} account found"];
        }

        try {
            $recipientId = $this->getRecipientId($contact, $platform);
            
            if (!$recipientId) {
                Log::error("UnifiedSocialInbox: No recipient ID found for contact", [
                    'contact_id' => $contactId,
                    'platform' => $platform,
                    'contact_phone' => $contact->phone
                ]);
                return ['success' => false, 'message' => 'Could not find recipient ID. Please sync messages again to update contact information.'];
            }
            
            Log::info("UnifiedSocialInbox: Sending message", [
                'platform' => $platform,
                'recipient_id' => $recipientId,
                'contact_id' => $contactId
            ]);
            
            switch ($platform) {
                case 'facebook':
                    $result = $this->facebookService->sendMessage($account, $recipientId, $message);
                    break;
                case 'instagram':
                    $result = $this->instagramService->sendMessage($account, $recipientId, $message);
                    break;
                case 'twitter':
                    $result = $this->twitterService->sendMessage($account, $recipientId, $message);
                    break;
                default:
                    return ['success' => false, 'message' => 'Unsupported platform'];
            }

            // Store sent message
            $chatData = [
                'uuid' => \Illuminate\Support\Str::uuid(),
                'organization_id' => $organizationId,
                'contact_id' => $contactId,
                'type' => 'outbound',
                'metadata' => json_encode([
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]),
                'status' => 'sent',
                'user_id' => $userId,
                'created_at' => now(),
            ];
            
            // Add platform fields only if column exists
            if (Schema::hasColumn('chats', 'platform')) {
                $chatData['platform'] = $platform;
                $chatData['platform_message_id'] = $result['message_id'] ?? null;
            }
            
            $chat = Chat::create($chatData);

            ChatLog::create([
                'contact_id' => $contactId,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => now(),
            ]);

            return ['success' => true, 'chat' => $chat, 'data' => $result];
        } catch (\Exception $e) {
            Log::error("Error sending {$platform} message: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get recipient ID from contact for a specific platform
     */
    protected function getRecipientId(Contact $contact, $platform)
    {
        // Extract platform ID from contact phone field
        // Format: platform_id (e.g., "fb_123456", "ig_789012")
        $phone = $contact->phone;
        
        if (str_starts_with($phone, $platform . '_')) {
            return str_replace($platform . '_', '', $phone);
        }

        // Try to get from platform_data in chats
        $lastChat = $contact->chats()
            ->where('platform', $platform)
            ->where('type', 'inbound')
            ->latest()
            ->first();

        if ($lastChat && $lastChat->platform_data) {
            $platformData = json_decode($lastChat->platform_data, true);
            // Prefer PSID over sender_id for sending messages
            return $platformData['psid'] ?? $platformData['sender_id'] ?? $platformData['from']['id'] ?? null;
        }

        return null;
    }

    /**
     * Get platform statistics
     */
    public function getPlatformStats($organizationId)
    {
        // Check if platform column exists
        if (!Schema::hasColumn('chats', 'platform')) {
            // Return default stats if migration not run
            return [
                'whatsapp' => Chat::where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->count(),
                'facebook' => 0,
                'instagram' => 0,
                'twitter' => 0,
                'total' => Chat::where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->count(),
            ];
        }

        $stats = [
            'whatsapp' => Chat::where('organization_id', $organizationId)
                ->where('platform', 'whatsapp')
                ->whereNull('deleted_at')
                ->count(),
            'facebook' => Chat::where('organization_id', $organizationId)
                ->where('platform', 'facebook')
                ->whereNull('deleted_at')
                ->count(),
            'instagram' => Chat::where('organization_id', $organizationId)
                ->where('platform', 'instagram')
                ->whereNull('deleted_at')
                ->count(),
            'twitter' => Chat::where('organization_id', $organizationId)
                ->where('platform', 'twitter')
                ->whereNull('deleted_at')
                ->count(),
        ];

        $stats['total'] = array_sum($stats);

        return $stats;
    }
}

