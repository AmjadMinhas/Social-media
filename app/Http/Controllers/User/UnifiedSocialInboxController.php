<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Services\UnifiedSocialInboxService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Redirect;

class UnifiedSocialInboxController extends BaseController
{
    protected $inboxService;

    public function __construct(UnifiedSocialInboxService $inboxService)
    {
        $this->inboxService = $inboxService;
    }

    /**
     * Display unified social inbox
     */
    public function index(Request $request, $contactUuid = null)
    {
        try {
            $organizationId = session()->get('current_organization');
            
            if (!$organizationId) {
                return Redirect::route('dashboard')->with(
                    'status', [
                        'type' => 'error',
                        'message' => __('Please select an organization first')
                    ]
                );
            }

            // Get filters
            $filters = [
                'platform' => $request->query('platform'),
                'type' => $request->query('type'),
                'search' => $request->query('search'),
                'per_page' => $request->query('per_page', 20),
            ];

            // Get contacts with messages
            $contacts = $this->inboxService->getUnifiedContacts($organizationId, $filters);

            // Get messages for selected contact
            $messages = null;
            $selectedContact = null;

            if ($contactUuid) {
                $selectedContact = \App\Models\Contact::where('uuid', $contactUuid)
                    ->where('organization_id', $organizationId)
                    ->with(['chats' => function($query) {
                        $query->whereNull('deleted_at')->orderBy('created_at', 'desc')->limit(10);
                    }])
                    ->first();

                if ($selectedContact) {
                    $messages = $this->inboxService->getUnifiedInbox($organizationId, [
                        'contact_id' => $selectedContact->id,
                        'per_page' => 50,
                    ]);
                }
            }

            // Get platform statistics
            $stats = $this->inboxService->getPlatformStats($organizationId);

            // Get connected platforms
            $connectedPlatforms = \App\Models\SocialAccount::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->get()
                ->pluck('platform')
                ->toArray();

            // Format messages for frontend if exists
            $formattedMessages = null;
            if ($messages) {
                $formattedMessages = [
                    'data' => $messages->items(),
                    'links' => $messages->linkCollection()->toArray(),
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ];
            }

            return Inertia::render('User/UnifiedSocialInbox/Index', [
                'title' => __('Unified Social Inbox'),
                'contacts' => $contacts ? [
                    'data' => $contacts->items(),
                    'links' => $contacts->linkCollection()->toArray(),
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ] : ['data' => [], 'links' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                'messages' => $formattedMessages,
                'selectedContact' => $selectedContact,
                'filters' => $filters,
                'stats' => $stats ?: ['whatsapp' => 0, 'facebook' => 0, 'instagram' => 0, 'twitter' => 0, 'total' => 0],
                'connectedPlatforms' => $connectedPlatforms ?: [],
            ]);
        } catch (\Exception $e) {
            \Log::error('UnifiedSocialInboxController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            );
        }
    }

    /**
     * Send message via unified inbox
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'platform' => 'required|in:whatsapp,facebook,instagram,twitter',
            'message' => 'required|string',
        ]);

        $organizationId = session()->get('current_organization');

        $result = $this->inboxService->sendMessage(
            $organizationId,
            $validated['platform'],
            $validated['contact_id'],
            $validated['message'],
            auth()->id()
        );

        if ($result['success']) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Message sent successfully!')
                ]
            );
        }

        return Redirect::back()->with(
            'status', [
                'type' => 'error',
                'message' => $result['message'] ?? __('Failed to send message')
            ]
        );
    }

    /**
     * Sync messages from all platforms
     */
    public function syncMessages(Request $request)
    {
        $organizationId = session()->get('current_organization');
        
        if (!$organizationId) {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Please select an organization first')
                ]
            );
        }

        \Illuminate\Support\Facades\Log::info('Sync messages requested', [
            'organization_id' => $organizationId,
            'user_id' => auth()->id()
        ]);

        try {
            $result = $this->inboxService->syncAllPlatforms($organizationId);

            \Illuminate\Support\Facades\Log::info('Sync messages completed', [
                'organization_id' => $organizationId,
                'result' => $result
            ]);

            $message = $result['message'] ?? __('Sync completed');
            if (isset($result['results'])) {
                $message .= sprintf(
                    ' - Facebook: %d, Instagram: %d, Twitter: %d, Total: %d',
                    $result['results']['facebook'] ?? 0,
                    $result['results']['instagram'] ?? 0,
                    $result['results']['twitter'] ?? 0,
                    $result['results']['total'] ?? 0
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sync messages error', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check if it's a permission error
            if (str_contains($e->getMessage(), 'pages_messaging')) {
                $message = __('Facebook Messenger permission error. Please disconnect and reconnect your Facebook page to grant the required permissions.');
            } else {
                $message = __('Sync failed: ') . $e->getMessage();
            }
            
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $message
                ]
            );
        }

        return Redirect::back()->with(
            'status', [
                'type' => $result['success'] ? 'success' : 'error',
                'message' => $message,
                'results' => $result['results'] ?? null,
            ]
        );
    }

    /**
     * Get platform statistics (API)
     */
    public function getStats(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $stats = $this->inboxService->getPlatformStats($organizationId);

        return response()->json($stats);
    }
}

