<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\SocialMedia\FacebookService;
use App\Services\SocialMedia\LinkedInService;
use App\Services\SocialMedia\InstagramService;
use App\Services\SocialMedia\TwitterService;
use App\Services\SocialMedia\TikTokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;

class SocialAccountController extends Controller
{
    protected $facebookService;
    protected $linkedinService;
    protected $instagramService;
    protected $twitterService;
    protected $tiktokService;

    public function __construct(
        FacebookService $facebookService, 
        LinkedInService $linkedinService,
        InstagramService $instagramService,
        TwitterService $twitterService,
        TikTokService $tiktokService
    ) {
        $this->facebookService = $facebookService;
        $this->linkedinService = $linkedinService;
        $this->instagramService = $instagramService;
        $this->twitterService = $twitterService;
        $this->tiktokService = $tiktokService;
    }

    /**
     * Display social accounts
     */
    /**
     * DEBUG ONLY - Test Facebook save logic without OAuth
     * Access: /debug/test-facebook-save
     * REMOVE THIS IN PRODUCTION
     */
    public function debugTestFacebookSave()
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $organizationId = session()->get('current_organization');
        $userId = auth()->id();

        // Simulate session data that would be set by OAuth
        $testPageId = 'test_page_' . time();
        $testPageName = 'Test Facebook Page';
        
        try {
            // Check if we have required data
            if (!$organizationId) {
                return response()->json([
                    'error' => 'No organization_id in session',
                    'session' => session()->all()
                ]);
            }

            if (!$userId) {
                return response()->json([
                    'error' => 'Not logged in',
                ]);
            }

            // Try to create a test account
            $uuid = (string) Str::uuid();
            
            $account = SocialAccount::create([
                'uuid' => $uuid,
                'organization_id' => (int) $organizationId,
                'user_id' => (int) $userId,
                'platform' => 'facebook',
                'platform_user_id' => $testPageId,
                'platform_username' => $testPageName,
                'access_token' => 'test_token_' . time(),
                'token_expires_at' => null,
                'platform_data' => [
                    'page_id' => $testPageId,
                    'page_name' => $testPageName,
                    'picture' => null,
                ],
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test account created successfully!',
                'account' => [
                    'id' => $account->id,
                    'uuid' => $account->uuid,
                    'organization_id' => $account->organization_id,
                    'user_id' => $account->user_id,
                    'platform' => $account->platform,
                    'platform_user_id' => $account->platform_user_id,
                    'is_active' => $account->is_active,
                ],
                'debug' => [
                    'session_org_id' => session()->get('current_organization'),
                    'auth_user_id' => auth()->id(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'debug' => [
                    'organization_id' => $organizationId,
                    'user_id' => $userId,
                    'session' => session()->all()
                ]
            ], 500);
        }
    }

    public function index()
    {
        try {
            // Check if table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('social_accounts')) {
                Log::error('social_accounts table does not exist');
                return Redirect::route('dashboard')->with(
                    'status', [
                        'type' => 'error',
                        'message' => __('Database table not found. Please run migrations.')
                    ]
                );
            }
            
            // Debug mode - show debug page
            if (request()->has('debug')) {
                return view('debug-social-accounts');
            }

            $organizationId = session()->get('current_organization');

            if (!$organizationId) {
                Log::warning('Social accounts index: No organization ID in session', [
                    'user_id' => auth()->id(),
                    'session_id' => session()->getId(),
                    'session_keys' => array_keys(session()->all()),
                    'all_session_data' => session()->all()
                ]);
                
                return Redirect::route('dashboard')->with(
                    'status', [
                        'type' => 'error',
                        'message' => __('Please select an organization first')
                    ]
                );
            }

            $accounts = SocialAccount::where('organization_id', $organizationId)
            ->whereNull('deleted_at') // Exclude soft-deleted accounts
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('Social accounts loaded', [
            'user_id' => auth()->id(),
            'organization_id' => $organizationId,
            'session_id' => session()->getId(),
            'accounts_count' => $accounts->count(),
            'account_details' => $accounts->map(function($account) {
                return [
                    'id' => $account->id,
                    'uuid' => $account->uuid,
                    'platform' => $account->platform,
                    'platform_user_id' => $account->platform_user_id,
                    'platform_username' => $account->platform_username,
                    'is_active' => $account->is_active,
                    'created_at' => $account->created_at,
                    'deleted_at' => $account->deleted_at
                ];
            })->toArray(),
            'total_accounts_in_db' => SocialAccount::where('organization_id', $organizationId)->count(),
            'total_active_accounts' => SocialAccount::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count(),
            'facebook_accounts' => SocialAccount::where('organization_id', $organizationId)
                ->where('platform', 'facebook')
                ->whereNull('deleted_at')
                ->get(['id', 'uuid', 'platform_user_id', 'platform_username', 'is_active'])
                ->toArray()
        ]);

            return Inertia::render('User/SocialAccounts/Index', [
                'title' => __('Social Media Accounts'),
                'accounts' => $accounts,
            ]);
        } catch (\Exception $e) {
            Log::error('SocialAccountController error: ' . $e->getMessage(), [
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
     * Redirect to Facebook OAuth
     */
    public function redirectToFacebook()
    {
        $state = bin2hex(random_bytes(16));
        $organizationId = session()->get('current_organization');
        
        if (!$organizationId) {
            Log::error('Facebook OAuth Redirect: No organization ID in session', [
                'user_id' => auth()->id(),
                'session_keys' => array_keys(session()->all())
            ]);
            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Please select an organization first')
            ]);
        }
        
        $isPopup = request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest';
        
        // Force re-auth if explicitly requested OR if organization has no existing accounts for this platform
        // This allows users to select which account to connect when connecting to a new organization
        $hasExistingAccounts = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', 'facebook')
            ->whereNull('deleted_at')
            ->exists();
        $forceReauth = request()->has('reconnect') || request()->has('force') || !$hasExistingAccounts;
        
        // Store both oauth_organization_id (for OAuth flow) and ensure current_organization is set
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => $organizationId]);
        session(['current_organization' => $organizationId]); // Ensure it's set
        session(['oauth_popup' => $isPopup]);

        Log::info('Facebook OAuth Redirect Started', [
            'user_id' => auth()->id(),
            'organization_id' => $organizationId,
            'is_popup' => $isPopup,
            'force_reauth' => $forceReauth,
            'has_existing_accounts' => $hasExistingAccounts,
            'state' => $state,
            'session_id' => session()->getId(),
        ]);

        return redirect($this->facebookService->getAuthorizationUrl($state, $forceReauth));
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function handleFacebookCallback(Request $request)
    {
        $debugInfo = [
            'user_id' => auth()->id(),
            'organization_id' => session('oauth_organization_id'),
            'is_popup' => session('oauth_popup'),
            'session_id' => session()->getId(),
            'request_params' => $request->all(),
            'session_state' => session('oauth_state'),
            'request_state' => $request->state,
        ];

        Log::info('Facebook OAuth Callback Received', $debugInfo);

        try {
            // Verify state
            if ($request->state !== session('oauth_state')) {
                Log::warning('Facebook OAuth state mismatch', [
                    'expected' => session('oauth_state'),
                    'received' => $request->state,
                    'session_id' => session()->getId(),
                    'user_id' => auth()->id()
                ]);
                throw new \Exception('Invalid state parameter');
            }

            if ($request->has('error')) {
                $errorMsg = $request->error_description ?? $request->error ?? 'Unknown error';
                Log::error('Facebook OAuth error from callback', [
                    'error' => $request->error,
                    'error_description' => $errorMsg,
                    'error_reason' => $request->error_reason ?? null,
                    'debug_info' => $debugInfo
                ]);
                throw new \Exception('User denied authorization: ' . $errorMsg);
            }

            if (!$request->has('code')) {
                Log::error('Facebook OAuth callback missing authorization code', [
                    'request_params' => $request->all(),
                    'debug_info' => $debugInfo
                ]);
                throw new \Exception('Missing authorization code');
            }

            Log::info('Facebook OAuth: Getting access token', [
                'code' => substr($request->code, 0, 10) . '...',
                'organization_id' => session('oauth_organization_id')
            ]);

            // Get access token
            $tokenData = $this->facebookService->getAccessToken($request->code);
            $shortLivedToken = $tokenData['access_token'] ?? null;

            if (!$shortLivedToken) {
                Log::error('Facebook OAuth: Failed to get access token', [
                    'token_response' => $tokenData,
                    'debug_info' => $debugInfo
                ]);
                throw new \Exception('Failed to get access token from Facebook');
            }

            Log::info('Facebook OAuth: Exchanging for long-lived token', [
                'has_short_token' => !empty($shortLivedToken),
                'organization_id' => session('oauth_organization_id')
            ]);

            // Exchange for long-lived token
            $longLivedTokenData = $this->facebookService->getLongLivedToken($shortLivedToken);
            $longLivedToken = $longLivedTokenData['access_token'] ?? null;

            if (!$longLivedToken) {
                Log::error('Facebook OAuth: Failed to get long-lived token', [
                    'token_response' => $longLivedTokenData,
                    'debug_info' => $debugInfo
                ]);
                throw new \Exception('Failed to get long-lived token from Facebook');
            }

            Log::info('Facebook OAuth: Getting user pages', [
                'has_long_token' => !empty($longLivedToken),
                'organization_id' => session('oauth_organization_id')
            ]);

            // Get user's pages
            $pages = $this->facebookService->getUserPages($longLivedToken);

            Log::info('Facebook OAuth: Pages retrieved', [
                'pages_count' => count($pages),
                'pages' => array_map(function($page) {
                    return [
                        'id' => $page['id'] ?? null,
                        'name' => $page['name'] ?? null,
                        'has_access_token' => !empty($page['access_token'] ?? null)
                    ];
                }, $pages),
                'organization_id' => session('oauth_organization_id')
            ]);

            if (empty($pages)) {
                // Try to get more information about why pages weren't found
                try {
                    $apiVersion = config('socialmedia.facebook.graph_api_version');
                    $baseUrl = "https://graph.facebook.com/{$apiVersion}";
                    
                    // Check if user has pages_show_list permission
                    $debugResponse = Http::get("{$baseUrl}/me/permissions", [
                        'access_token' => $longLivedToken,
                    ]);
                    
                    $permissions = $debugResponse->json()['data'] ?? [];
                    $hasPagesPermission = collect($permissions)->contains(function($perm) {
                        return ($perm['permission'] === 'pages_show_list' || $perm['permission'] === 'pages_read_engagement') 
                            && $perm['status'] === 'granted';
                    });
                    
                    Log::warning('Facebook OAuth: No pages found', [
                        'user_id' => auth()->id(),
                        'organization_id' => session('oauth_organization_id'),
                        'has_token' => !empty($longLivedToken),
                        'permissions' => $permissions,
                        'has_pages_permission' => $hasPagesPermission,
                        'token_preview' => substr($longLivedToken, 0, 20) . '...'
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Facebook OAuth: Could not check permissions', [
                        'error' => $e->getMessage()
                    ]);
                }
                
                $errorMessage = __('No Facebook pages found. Please ensure: 1) You have created a Facebook page, 2) You have granted pages_show_list permission, 3) You are an admin of at least one page.');
                
                // If in popup, return error view instead of redirecting
                if (session('oauth_popup')) {
                    $isPopup = session('oauth_popup');
                    session()->forget('oauth_popup');
                    Log::warning('Facebook OAuth: Returning error to popup', [
                        'error' => $errorMessage,
                        'is_popup' => $isPopup
                    ]);
                    return view('oauth-callback', ['error' => $errorMessage]);
                }
                
                return redirect('/social-accounts')->with('status', [
                    'type' => 'error',
                    'message' => $errorMessage
                ]);
            }

            // Store in session to show page selection
            session(['facebook_pages' => $pages]);
            session(['facebook_user_token' => $longLivedToken]);

            Log::info('Facebook OAuth: Stored pages in session', [
                'pages_count' => count($pages),
                'session_has_pages' => !empty(session('facebook_pages')),
                'organization_id' => session('oauth_organization_id')
            ]);

            // If in popup, render in popup-friendly way
            if (session('oauth_popup')) {
                // Make sure we're not redirecting to social-accounts inside popup
                // Render the page selection directly
                return Inertia::render('User/SocialAccounts/SelectFacebookPage', [
                    'title' => __('Select Facebook Page'),
                    'pages' => $pages,
                    'isPopup' => true,
                ]);
            }

            return Inertia::render('User/SocialAccounts/SelectFacebookPage', [
                'title' => __('Select Facebook Page'),
                'pages' => $pages,
                'isPopup' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('Facebook OAuth callback error: ' . $e->getMessage());
            
            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['error' => __('Failed to connect Facebook: ') . $e->getMessage()]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to connect Facebook: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Save selected Facebook page
     */
    public function saveFacebookPage(Request $request)
    {
        $isPopup = session('oauth_popup');
        
        // Validate request - handle validation errors for popup mode
        try {
        $request->validate([
            'page_id' => 'required|string',
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // If in popup mode, ALWAYS return JSON (even if not explicitly requested)
            if ($isPopup) {
                $errors = $e->errors();
                $errorMessage = __('Validation failed');
                if (isset($errors['page_id'])) {
                    $errorMessage = implode(', ', $errors['page_id']);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            }
            
            // If request expects JSON, return JSON error
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                $errors = $e->errors();
                $errorMessage = __('Validation failed');
                if (isset($errors['page_id'])) {
                    $errorMessage = implode(', ', $errors['page_id']);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            }
            
            // Otherwise, let Laravel handle it normally (redirect with errors)
            throw $e;
        }

        $debugInfo = [
            'user_id' => auth()->id(),
            'organization_id' => session('oauth_organization_id'),
            'is_popup' => session('oauth_popup'),
            'session_id' => session()->getId(),
            'request_page_id' => $request->page_id,
            'has_pages_in_session' => !empty(session('facebook_pages')),
            'has_token_in_session' => !empty(session('facebook_user_token')),
            'pages_count' => count(session('facebook_pages', [])),
        ];

        Log::info('Facebook Save Page: Request received', $debugInfo);

        try {
            $pages = session('facebook_pages', []);
            $userToken = session('facebook_user_token');
            
            // CRITICAL: Always use current_organization from session, not oauth_organization_id
            // This ensures we use the organization the user is currently viewing, not the one
            // from when they started the OAuth flow (which might have changed)
            $organizationId = session('current_organization');
            
            // Fallback to oauth_organization_id only if current_organization is not set
            // This handles edge cases where session might have been cleared
            if (!$organizationId) {
                $organizationId = session('oauth_organization_id');
            }
            
            // If still null, try to get from authenticated user's organization
            if (!$organizationId && auth()->check()) {
                $user = auth()->user();
                // Try to get organization from user's team/organization relationship
                // This is a fallback in case session is lost
            }

            Log::info('Facebook Save Page: Session data check', [
                'pages_count' => count($pages),
                'has_token' => !empty($userToken),
                'organization_id_from_oauth' => session('oauth_organization_id'),
                'organization_id_from_current' => session('current_organization'),
                'organization_id_final' => $organizationId,
                'organization_id_type' => gettype($organizationId),
                'organization_id_empty' => empty($organizationId),
                'session_id' => session()->getId(),
                'all_session_keys' => array_keys(session()->all()),
                'full_session' => session()->all()
            ]);

            // Check if required session data exists
            if (empty($pages)) {
                Log::error('Facebook Save Page: No pages in session', $debugInfo);
                throw new \Exception('Session expired. Please try connecting again.');
            }

            // CRITICAL: Validate organization_id is set and not null/empty
            if (empty($organizationId) || $organizationId === null) {
                Log::error('Facebook Save Page: No organization ID', array_merge($debugInfo, [
                    'session_oauth_org_id' => session('oauth_organization_id'),
                    'session_current_org' => session('current_organization'),
                    'all_session_data' => session()->all()
                ]));
                throw new \Exception('Organization ID is missing. Please try connecting again.');
            }
            
            // Ensure organization_id is an integer
            $organizationId = (int) $organizationId;
            if ($organizationId <= 0) {
                Log::error('Facebook Save Page: Invalid organization ID', [
                    'organization_id' => $organizationId,
                    'debug_info' => $debugInfo
                ]);
                throw new \Exception('Invalid organization ID. Please try connecting again.');
            }

            $selectedPage = collect($pages)->firstWhere('id', $request->page_id);

            Log::info('Facebook Save Page: Page selection', [
                'requested_page_id' => $request->page_id,
                'found_page' => !empty($selectedPage),
                'page_name' => $selectedPage['name'] ?? 'NOT FOUND',
                'available_page_ids' => collect($pages)->pluck('id')->toArray()
            ]);

            if (!$selectedPage) {
                Log::error('Facebook Save Page: Invalid page selected', [
                    'requested_page_id' => $request->page_id,
                    'available_pages' => collect($pages)->map(function($p) {
                        return ['id' => $p['id'] ?? null, 'name' => $p['name'] ?? null];
                    })->toArray(),
                    'debug_info' => $debugInfo
                ]);
                throw new \Exception('Invalid page selected');
            }

            // Get page long-lived token
            $pageToken = $selectedPage['access_token'];
            $longLivedPageToken = $this->facebookService->getPageLongLivedToken(
                $selectedPage['id'],
                $pageToken
            );

            // Check if account already exists in THIS organization (including soft-deleted)
            // Also check if this page is connected to a DIFFERENT organization
            $existingAccount = SocialAccount::withTrashed()
                ->where('organization_id', $organizationId)
                ->where('platform', 'facebook')
                ->where('platform_user_id', $selectedPage['id'])
                ->first();
            
            // Check if this page is connected to another organization
            $accountInOtherOrg = SocialAccount::where('platform', 'facebook')
                ->where('platform_user_id', $selectedPage['id'])
                ->where('organization_id', '!=', $organizationId)
                ->whereNull('deleted_at')
                ->first();
            
            if ($accountInOtherOrg && !$existingAccount) {
                Log::warning('Facebook Save Page: Page already connected to another organization', [
                    'page_id' => $selectedPage['id'],
                    'page_name' => $selectedPage['name'],
                    'current_org_id' => $organizationId,
                    'other_org_id' => $accountInOtherOrg->organization_id,
                    'other_org_account_id' => $accountInOtherOrg->id
                ]);
                // Allow connection - same page can be connected to multiple organizations
                // This is intentional behavior
            }

            Log::info('Facebook Save Page: Account check', [
                'page_id' => $selectedPage['id'],
                'page_name' => $selectedPage['name'],
                'existing_account_found' => !empty($existingAccount),
                'existing_account_id' => $existingAccount->id ?? null,
                'existing_account_uuid' => $existingAccount->uuid ?? null,
                'existing_account_active' => $existingAccount->is_active ?? null,
                'all_facebook_accounts_for_org' => SocialAccount::where('organization_id', $organizationId)
                    ->where('platform', 'facebook')
                    ->get(['id', 'uuid', 'platform_user_id', 'platform_username', 'is_active'])
                    ->toArray()
            ]);

            // Ensure UUID is always set (fallback to model boot method if needed)
            $uuid = $existingAccount ? $existingAccount->uuid : (string) Str::uuid();
            
            // Validate required fields before creating
            if (!$organizationId) {
                throw new \Exception('Organization ID is missing. Please try again.');
            }
            
            // Get user ID
            $userId = auth()->id();
            if (!$userId) {
                throw new \Exception('User not authenticated. Please log in again.');
            }
            
            if (!$selectedPage['id'] || !$selectedPage['name']) {
                throw new \Exception('Invalid page data received from Facebook.');
            }

            $accountData = [
                'uuid' => $uuid,
                'organization_id' => (int) $organizationId,
                'user_id' => (int) $userId,
                'platform' => 'facebook',
                'platform_user_id' => $selectedPage['id'],
                'platform_username' => $selectedPage['name'],
                'access_token' => $longLivedPageToken ?? $pageToken,
                'token_expires_at' => null, // Facebook page tokens don't expire
                'platform_data' => [
                    'page_id' => $selectedPage['id'],
                    'page_name' => $selectedPage['name'],
                    'picture' => $selectedPage['picture']['data']['url'] ?? null,
                ],
                'is_active' => true,
            ];

            if ($existingAccount) {
                // If account was soft-deleted, restore it first
                if ($existingAccount->trashed()) {
                    Log::info('Facebook Save Page: Restoring soft-deleted account', [
                        'account_id' => $existingAccount->id,
                        'account_uuid' => $existingAccount->uuid
                    ]);
                    $existingAccount->restore();
                }
                
                Log::info('Facebook Save Page: Updating existing account', [
                    'account_id' => $existingAccount->id,
                    'account_uuid' => $existingAccount->uuid,
                    'old_is_active' => $existingAccount->is_active,
                    'new_is_active' => $accountData['is_active'],
                    'was_trashed' => $existingAccount->trashed()
                ]);
                
                $existingAccount->update($accountData);
                
                Log::info('Facebook Save Page: Account updated successfully', [
                    'account_id' => $existingAccount->id,
                    'account_uuid' => $existingAccount->uuid,
                    'is_active' => $existingAccount->fresh()->is_active
                ]);
                
                $message = __('Facebook page reconnected successfully!');
            } else {
                // Double-check organization_id is not null/empty
                if (empty($organizationId) || $organizationId === null) {
                    Log::error('Facebook Save Page: organization_id is null/empty when creating account', [
                        'organization_id' => $organizationId,
                        'session_oauth_org_id' => session('oauth_organization_id'),
                        'session_current_org' => session('current_organization'),
                        'debug_info' => $debugInfo
                    ]);
                    throw new \Exception('Organization ID is missing. Please try connecting again.');
                }
                
                // Ensure userId is set
                if (empty($userId) || $userId === null) {
                    Log::error('Facebook Save Page: user_id is null/empty when creating account', [
                        'user_id' => $userId,
                        'auth_id' => auth()->id(),
                        'debug_info' => $debugInfo
                    ]);
                    throw new \Exception('User ID is missing. Please log in again.');
                }
                
                Log::info('Facebook Save Page: Creating new account', [
                    'uuid' => $uuid,
                    'organization_id' => $organizationId,
                    'organization_id_type' => gettype($organizationId),
                    'user_id' => $userId,
                    'user_id_type' => gettype($userId),
                    'platform_user_id' => $selectedPage['id'],
                    'platform_username' => $selectedPage['name'],
                    'account_data_keys' => array_keys($accountData),
                    'account_data_organization_id' => $accountData['organization_id'] ?? 'MISSING',
                    'account_data_user_id' => $accountData['user_id'] ?? 'MISSING'
                ]);
                
                // Use create method with explicit type casting to ensure all fields are set
                $createData = [
                    'uuid' => (string) $uuid,
                    'organization_id' => (int) $organizationId, // Explicitly cast to int
                    'user_id' => (int) $userId, // Explicitly cast to int
                    'platform' => 'facebook',
                    'platform_user_id' => (string) $selectedPage['id'],
                    'platform_username' => (string) $selectedPage['name'],
                    'access_token' => (string) ($longLivedPageToken ?? $pageToken),
                    'token_expires_at' => null,
                    'platform_data' => [
                        'page_id' => $selectedPage['id'],
                        'page_name' => $selectedPage['name'],
                        'picture' => $selectedPage['picture']['data']['url'] ?? null,
                    ],
                    'is_active' => true,
                ];
                
                Log::info('Facebook Save Page: Data to create', [
                    'create_data' => array_merge($createData, [
                        'access_token' => substr($createData['access_token'], 0, 20) . '...'
                    ]),
                    'organization_id_in_data' => $createData['organization_id'],
                    'user_id_in_data' => $createData['user_id']
                ]);
                
                $newAccount = SocialAccount::create($createData);
                
                Log::info('Facebook Save Page: Account created successfully', [
                    'account_id' => $newAccount->id,
                    'account_uuid' => $newAccount->uuid,
                    'is_active' => $newAccount->is_active,
                    'organization_id' => $newAccount->organization_id
                ]);
                
                $message = __('Facebook page connected successfully!');
            }
            
            // Verify account was saved
            $savedAccount = SocialAccount::where('organization_id', $organizationId)
                ->where('platform', 'facebook')
                ->where('platform_user_id', $selectedPage['id'])
                ->first();
                
            Log::info('Facebook Save Page: Verification after save', [
                'account_found' => !empty($savedAccount),
                'account_id' => $savedAccount->id ?? null,
                'account_uuid' => $savedAccount->uuid ?? null,
                'account_is_active' => $savedAccount->is_active ?? null,
                'total_facebook_accounts' => SocialAccount::where('organization_id', $organizationId)
                    ->where('platform', 'facebook')
                    ->count()
            ]);

            // Clear session data
            $isPopup = session('oauth_popup');
            session()->forget(['facebook_pages', 'facebook_user_token', 'oauth_state', 'oauth_organization_id', 'oauth_popup']);

            // Check if opened in popup - return JSON response for AJAX/fetch requests
            if ($isPopup) {
                // If request expects JSON (from fetch/AJAX), return JSON
                if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                    return response()->json([
                        'success' => true,
                        'message' => $message
                    ]);
                }
                // Otherwise return the callback view for traditional form submissions
                return view('oauth-callback', ['status' => ['type' => 'success', 'message' => $message]]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Facebook save page error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'session_data' => [
                    'has_pages' => !empty(session('facebook_pages')),
                    'has_token' => !empty(session('facebook_user_token')),
                    'has_org_id' => !empty(session('oauth_organization_id')),
                    'is_popup' => session('oauth_popup'),
                ]
            ]);
            
            $isPopup = session('oauth_popup');
            
            // If in popup mode, ALWAYS return JSON (even if not explicitly requested)
            // This prevents HTML error pages from being returned
            if ($isPopup) {
                return response()->json([
                    'success' => false,
                    'message' => __('Failed to save Facebook page: ') . $e->getMessage()
                ], 400);
            }
            
            // If request expects JSON, return JSON error
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => __('Failed to save Facebook page: ') . $e->getMessage()
                ], 400);
            }
            
            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to save Facebook page: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Redirect to LinkedIn OAuth
     */
    public function redirectToLinkedIn()
    {
        $state = bin2hex(random_bytes(16));
        $organizationId = session()->get('current_organization');
        $isPopup = request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest';
        
        // Force re-auth if organization has no existing accounts for this platform
        // Note: LinkedIn doesn't support forcing account selection via OAuth parameters,
        // but forcing re-auth ensures a fresh authorization flow
        $hasExistingAccounts = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', 'linkedin')
            ->whereNull('deleted_at')
            ->exists();
        $forceReauth = request()->has('reconnect') || request()->has('force') || !$hasExistingAccounts;
        
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => $organizationId]);
        session(['oauth_popup' => $isPopup]);

        // LinkedIn doesn't support force re-auth parameter, but we can add prompt=consent if needed
        // For now, just redirect normally - users will need to log out of LinkedIn to switch accounts
        return redirect($this->linkedinService->getAuthorizationUrl($state));
    }

    /**
     * Handle LinkedIn OAuth callback
     */
    public function handleLinkedInCallback(Request $request)
    {
        try {
            // Verify state
            if ($request->state !== session('oauth_state')) {
                throw new \Exception('Invalid state parameter');
            }

            if ($request->has('error')) {
                throw new \Exception('User denied authorization: ' . $request->error_description);
            }

            // Get access token
            $tokenData = $this->linkedinService->getAccessToken($request->code);
            $accessToken = $tokenData['access_token'];
            $expiresIn = $tokenData['expires_in'] ?? 5184000; // Default 60 days
            $refreshToken = $tokenData['refresh_token'] ?? null;

            // Get user profile
            $profile = $this->linkedinService->getUserProfile($accessToken);
            $personId = $profile['sub'] ?? null;

            if (!$personId) {
                throw new \Exception('Failed to get LinkedIn profile');
            }

            $organizationId = session('oauth_organization_id');

            // Check if account already exists (including soft-deleted)
            // Use withTrashed() to find both active and soft-deleted accounts
            $existingAccount = SocialAccount::withTrashed()
                ->where('organization_id', $organizationId)
                ->where('platform', 'linkedin')
                ->where('platform_user_id', $personId)
                ->first();

            Log::info('LinkedIn OAuth: Account check', [
                'organization_id' => $organizationId,
                'person_id' => $personId,
                'existing_account_found' => !empty($existingAccount),
                'existing_account_id' => $existingAccount->id ?? null,
                'existing_account_trashed' => $existingAccount ? $existingAccount->trashed() : null,
            ]);

            $accountData = [
                'uuid' => $existingAccount ? $existingAccount->uuid : Str::uuid(),
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
                'platform' => 'linkedin',
                'platform_user_id' => $personId,
                'platform_username' => $profile['name'] ?? $profile['email'] ?? 'LinkedIn User',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => Carbon::now()->addSeconds($expiresIn),
                'platform_data' => [
                    'person_urn' => "urn:li:person:{$personId}",
                    'email' => $profile['email'] ?? null,
                    'name' => $profile['name'] ?? null,
                    'picture' => $profile['picture'] ?? null,
                ],
                'is_active' => true,
            ];

            if ($existingAccount) {
                // If account was soft-deleted, restore it first
                if ($existingAccount->trashed()) {
                    Log::info('LinkedIn OAuth: Restoring soft-deleted account', [
                        'account_id' => $existingAccount->id
                    ]);
                    $existingAccount->restore();
                }
                
                Log::info('LinkedIn OAuth: Updating existing account', [
                    'account_id' => $existingAccount->id
                ]);
                
                $existingAccount->update($accountData);
                $message = __('LinkedIn account reconnected successfully!');
            } else {
                // Double-check if account exists (race condition protection)
                $duplicateCheck = SocialAccount::withTrashed()
                    ->where('organization_id', $organizationId)
                    ->where('platform', 'linkedin')
                    ->where('platform_user_id', $personId)
                    ->lockForUpdate()
                    ->first();
                
                if ($duplicateCheck) {
                    Log::warning('LinkedIn OAuth: Found duplicate account on second check', [
                        'account_id' => $duplicateCheck->id,
                        'trashed' => $duplicateCheck->trashed()
                    ]);
                    
                    if ($duplicateCheck->trashed()) {
                        $duplicateCheck->restore();
                    }
                    $duplicateCheck->update($accountData);
                    $message = __('LinkedIn account reconnected successfully!');
                } else {
                    Log::info('LinkedIn OAuth: Creating new account', [
                        'organization_id' => $organizationId,
                        'person_id' => $personId
                    ]);
                    
                    try {
                        SocialAccount::create($accountData);
                        $message = __('LinkedIn account connected successfully!');
                    } catch (\Illuminate\Database\QueryException $e) {
                        // If still getting duplicate error, try to find and update
                        if (str_contains($e->getMessage(), 'Duplicate entry')) {
                            Log::warning('LinkedIn OAuth: Duplicate entry error, attempting to find and update', [
                                'error' => $e->getMessage()
                            ]);
                            
                            $duplicateAccount = SocialAccount::withTrashed()
                                ->where('organization_id', $organizationId)
                                ->where('platform', 'linkedin')
                                ->where('platform_user_id', $personId)
                                ->first();
                            
                            if ($duplicateAccount) {
                                if ($duplicateAccount->trashed()) {
                                    $duplicateAccount->restore();
                                }
                                $duplicateAccount->update($accountData);
                                $message = __('LinkedIn account reconnected successfully!');
                            } else {
                                throw $e;
                            }
                        } else {
                            throw $e;
                        }
                    }
                }
            }

            // Clear session data
            session()->forget(['oauth_state', 'oauth_organization_id']);

            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['status' => ['type' => 'success', 'message' => $message]]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('LinkedIn OAuth callback error: ' . $e->getMessage());
            
            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['error' => __('Failed to connect LinkedIn: ') . $e->getMessage()]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to connect LinkedIn: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete social account
     */
    public function destroy($uuid)
    {
        try {
            $organizationId = session()->get('current_organization');

            $account = SocialAccount::where('uuid', $uuid)
                ->where('organization_id', $organizationId)
                ->firstOrFail();

            $account->delete();

            return redirect()->back()->with('status', [
                'type' => 'success',
                'message' => __('Social account disconnected successfully!')
            ]);

        } catch (\Exception $e) {
            Log::error('Delete social account error: ' . $e->getMessage());
            
            return redirect()->back()->with('status', [
                'type' => 'error',
                'message' => __('Failed to disconnect account: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Verify account connection
     */
    public function verify($uuid)
    {
        try {
            $organizationId = session()->get('current_organization');

            $account = SocialAccount::where('uuid', $uuid)
                ->where('organization_id', $organizationId)
                ->firstOrFail();

            $isValid = false;

            switch ($account->platform) {
                case 'facebook':
                    $isValid = $this->facebookService->verifyAccount($account);
                    break;
                case 'linkedin':
                    $isValid = $this->linkedinService->verifyAccount($account);
                    break;
                case 'instagram':
                    $isValid = $this->instagramService->verifyAccount($account);
                    break;
                case 'twitter':
                    $isValid = $this->twitterService->verifyAccount($account);
                    break;
            }

            if ($isValid) {
                $account->update(['is_active' => true]);
                $message = __('Account verified successfully!');
                $type = 'success';
            } else {
                $account->update(['is_active' => false]);
                $message = __('Account verification failed. Please reconnect.');
                $type = 'error';
            }

            return redirect()->back()->with('status', [
                'type' => $type,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Verify social account error: ' . $e->getMessage());
            
            return redirect()->back()->with('status', [
                'type' => 'error',
                'message' => __('Failed to verify account: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Redirect to Instagram OAuth
     * Note: Instagram uses Facebook's OAuth system, so users will see Facebook's login dialog.
     * This is expected behavior - the callback URL and scopes ensure it's for Instagram.
     */
    public function redirectToInstagram()
    {
        $state = bin2hex(random_bytes(16));
        $organizationId = session()->get('current_organization');
        $isPopup = request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest';
        
        // Force re-auth if organization has no existing accounts OR if explicitly requested
        // Instagram uses Facebook OAuth, so forcing re-auth allows account selection
        $hasExistingAccounts = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', 'instagram')
            ->whereNull('deleted_at')
            ->exists();
        $forceReauth = request()->has('reconnect') || request()->has('force') || !$hasExistingAccounts;
        
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => $organizationId]);
        session(['oauth_popup' => $isPopup]);

        // Force re-authorization for Instagram to ensure all permissions (including pages_show_list) are granted
        // Instagram will use its own callback URL (/auth/instagram/callback) and Instagram-specific scopes
        return redirect($this->instagramService->getAuthorizationUrl($state, $forceReauth));
    }

    /**
     * Handle Instagram OAuth callback
     */
    public function handleInstagramCallback(Request $request)
    {
        try {
            Log::info('Instagram OAuth callback received', [
                'has_code' => $request->has('code'),
                'has_error' => $request->has('error'),
                'state' => $request->state,
                'session_state' => session('oauth_state'),
                'oauth_popup' => session('oauth_popup'),
            ]);

            // Verify state
            if ($request->state !== session('oauth_state')) {
                throw new \Exception('Invalid state parameter');
            }

            if ($request->has('error')) {
                throw new \Exception('User denied authorization: ' . $request->error_description);
            }

            // Get access token
            $tokenData = $this->instagramService->getAccessToken($request->code);
            $shortLivedToken = $tokenData['access_token'];

            // Exchange for long-lived token
            $longLivedTokenData = $this->instagramService->getLongLivedToken($shortLivedToken);
            $longLivedToken = $longLivedTokenData['access_token'];

            // Try to get Instagram accounts using the user token
            $accounts = $this->instagramService->getInstagramAccounts($longLivedToken);
            
            // If no accounts found, try using existing Facebook page connections
            if (empty($accounts)) {
                $organizationId = session('oauth_organization_id');
                $existingFacebookAccounts = SocialAccount::where('organization_id', $organizationId)
                    ->where('platform', 'facebook')
                    ->where('is_active', true)
                    ->get();
                
                Log::info('Instagram: No accounts found via user token, trying existing Facebook pages', [
                    'facebook_accounts_count' => $existingFacebookAccounts->count(),
                ]);
                
                // Try to get Instagram accounts from existing Facebook page tokens
                foreach ($existingFacebookAccounts as $fbAccount) {
                    $pageToken = $fbAccount->access_token;
                    $pageId = $fbAccount->platform_user_id;
                    
                    try {
                        // Check if this page has an Instagram account
                        $igResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}", [
                            'fields' => 'instagram_business_account{id,username}',
                            'access_token' => $pageToken,
                        ]);
                        
                        if ($igResponse->successful()) {
                            $igData = $igResponse->json();
                            
                            if (isset($igData['instagram_business_account'])) {
                                $igBusinessAccount = $igData['instagram_business_account'];
                                $igAccountId = is_array($igBusinessAccount) ? ($igBusinessAccount['id'] ?? null) : $igBusinessAccount;
                                
                                if ($igAccountId) {
                                    // Get Instagram account details
                                    $detailsResponse = Http::get("https://graph.facebook.com/v18.0/{$igAccountId}", [
                                        'fields' => 'id,username,profile_picture_url',
                                        'access_token' => $pageToken,
                                    ]);
                                    
                                    if ($detailsResponse->successful()) {
                                        $details = $detailsResponse->json();
                                        $accounts[] = [
                                            'id' => $igAccountId,
                                            'username' => $details['username'] ?? 'Unknown',
                                            'profile_picture' => $details['profile_picture_url'] ?? null,
                                            'page_id' => $pageId,
                                            'page_name' => $fbAccount->platform_username,
                                            'access_token' => $pageToken,
                                        ];
                                        
                                        Log::info('Instagram: Found Instagram account via existing Facebook page', [
                                            'ig_account_id' => $igAccountId,
                                            'page_id' => $pageId,
                                        ]);
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Instagram: Error checking Facebook page for Instagram account', [
                            'page_id' => $pageId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            Log::info('Instagram accounts retrieved', [
                'accounts_count' => count($accounts),
                'oauth_popup' => session('oauth_popup'),
                'account_ids' => array_column($accounts, 'id'),
            ]);

            if (empty($accounts)) {
                $isPopup = session('oauth_popup');
                session()->forget('oauth_popup');
                
                if ($isPopup) {
                    return view('oauth-callback', ['error' => __('No Instagram Business accounts found. Please connect a Facebook page with an Instagram Business account.')]);
                }
                
                return redirect('/social-accounts')->with('status', [
                    'type' => 'error',
                    'message' => __('No Instagram Business accounts found. Please connect a Facebook page with an Instagram Business account.')
                ]);
            }

            // Store in session to show account selection
            session(['instagram_accounts' => $accounts]);
            session(['instagram_user_token' => $longLivedToken]);

            // If in popup, render in popup-friendly way
            if (session('oauth_popup')) {
                return Inertia::render('User/SocialAccounts/SelectInstagramAccount', [
                    'title' => __('Select Instagram Account'),
                    'accounts' => $accounts,
                    'isPopup' => true,
                ]);
            }

            return Inertia::render('User/SocialAccounts/SelectInstagramAccount', [
                'title' => __('Select Instagram Account'),
                'accounts' => $accounts,
            ]);

        } catch (\Exception $e) {
            Log::error('Instagram OAuth callback error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'oauth_popup' => session('oauth_popup'),
            ]);
            
            // Check if opened in popup
            $isPopup = session('oauth_popup');
            if ($isPopup) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['error' => __('Failed to connect Instagram: ') . $e->getMessage()]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to connect Instagram: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Save selected Instagram account
     */
    public function saveInstagramAccount(Request $request)
    {
        $request->validate([
            'account_id' => 'required|string',
        ]);

        try {
            $accounts = session('instagram_accounts', []);
            $userToken = session('instagram_user_token');
            $organizationId = session('oauth_organization_id');

            $selectedAccount = collect($accounts)->firstWhere('id', $request->account_id);

            if (!$selectedAccount) {
                throw new \Exception('Invalid account selected');
            }

            // Check if account already exists (including soft-deleted)
            $existingAccount = SocialAccount::withTrashed()
                ->where('organization_id', $organizationId)
                ->where('platform', 'instagram')
                ->where('platform_user_id', $selectedAccount['id'])
                ->first();

            $accountData = [
                'uuid' => $existingAccount ? $existingAccount->uuid : Str::uuid(),
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
                'platform' => 'instagram',
                'platform_user_id' => $selectedAccount['id'],
                'platform_username' => $selectedAccount['username'],
                'access_token' => $selectedAccount['access_token'],
                'token_expires_at' => null, // Instagram tokens are long-lived
                'platform_data' => [
                    'ig_account_id' => $selectedAccount['id'],
                    'username' => $selectedAccount['username'],
                    'page_id' => $selectedAccount['page_id'],
                    'page_name' => $selectedAccount['page_name'],
                    'profile_picture' => $selectedAccount['profile_picture'] ?? null,
                ],
                'is_active' => true,
            ];

            if ($existingAccount) {
                // If account was soft-deleted, restore it first
                if ($existingAccount->trashed()) {
                    $existingAccount->restore();
                }
                $existingAccount->update($accountData);
                $message = __('Instagram account reconnected successfully!');
            } else {
                SocialAccount::create($accountData);
                $message = __('Instagram account connected successfully!');
            }

            // Clear session data
            $isPopup = session('oauth_popup');
            session()->forget(['instagram_accounts', 'instagram_user_token', 'oauth_state', 'oauth_organization_id', 'oauth_popup']);

            // Check if opened in popup - return JSON response for AJAX/fetch requests
            if ($isPopup) {
                // If request expects JSON (from fetch/AJAX), return JSON
                if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                    return response()->json([
                        'success' => true,
                        'message' => $message
                    ]);
                }
                // Otherwise return the callback view for traditional form submissions
                return view('oauth-callback', ['status' => ['type' => 'success', 'message' => $message]]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Instagram save account error: ' . $e->getMessage());
            
            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to save Instagram account: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Redirect to Twitter OAuth
     */
    public function redirectToTwitter()
    {
        $state = bin2hex(random_bytes(16));
        $organizationId = session()->get('current_organization');
        $isPopup = request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest';
        
        // Force re-auth if organization has no existing accounts for this platform
        // Note: Twitter OAuth 2.0 doesn't support forcing account selection via OAuth parameters
        $hasExistingAccounts = SocialAccount::where('organization_id', $organizationId)
            ->where('platform', 'twitter')
            ->whereNull('deleted_at')
            ->exists();
        $forceReauth = request()->has('reconnect') || request()->has('force') || !$hasExistingAccounts;
        
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => $organizationId]);
        session(['oauth_popup' => $isPopup]);

        // Twitter doesn't support force re-auth parameter, but we can still redirect
        // Users will need to log out of Twitter to switch accounts
        return redirect($this->twitterService->getAuthorizationUrl($state));
    }

    /**
     * Handle Twitter OAuth callback
     */
    public function handleTwitterCallback(Request $request)
    {
        try {
            // Verify state
            if ($request->state !== session('oauth_state')) {
                throw new \Exception('Invalid state parameter');
            }

            if ($request->has('error')) {
                throw new \Exception('User denied authorization: ' . $request->error_description);
            }

            // Get access token
            try {
                $tokenData = $this->twitterService->getAccessToken($request->code);
            } catch (\Exception $e) {
                // Re-throw with more context if it's already a detailed error
                throw new \Exception($e->getMessage());
            }
            
            if (!$tokenData || !isset($tokenData['access_token'])) {
                Log::error('Twitter OAuth: Token data missing access_token', [
                    'token_data_keys' => $tokenData ? array_keys($tokenData) : 'null',
                    'token_data' => $tokenData,
                ]);
                throw new \Exception('Invalid token response from Twitter: access_token not found in response');
            }
            
            $accessToken = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? 7200; // Default 2 hours

            // Get user profile
            $profile = $this->twitterService->getUserProfile($accessToken);
            
            if (!$profile || !is_array($profile)) {
                throw new \Exception('Failed to get Twitter profile: Invalid response');
            }
            
            $userId = $profile['id'] ?? null;

            if (!$userId) {
                throw new \Exception('Failed to get Twitter user ID from profile');
            }

            $organizationId = session('oauth_organization_id');

            // Check if account already exists (including soft-deleted)
            $existingAccount = SocialAccount::withTrashed()
                ->where('organization_id', $organizationId)
                ->where('platform', 'twitter')
                ->where('platform_user_id', $userId)
                ->first();

            $accountData = [
                'uuid' => $existingAccount ? $existingAccount->uuid : Str::uuid(),
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
                'platform' => 'twitter',
                'platform_user_id' => $userId,
                'platform_username' => $profile['username'] ?? $profile['name'] ?? 'Twitter User',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => Carbon::now()->addSeconds($expiresIn),
                'platform_data' => [
                    'user_id' => $userId,
                    'username' => $profile['username'] ?? null,
                    'name' => $profile['name'] ?? null,
                    'profile_image' => $profile['profile_image_url'] ?? null,
                ],
                'is_active' => true,
            ];

            if ($existingAccount) {
                // If account was soft-deleted, restore it first
                if ($existingAccount->trashed()) {
                    $existingAccount->restore();
                }
                $existingAccount->update($accountData);
                $message = __('Twitter account reconnected successfully!');
            } else {
                SocialAccount::create($accountData);
                $message = __('Twitter account connected successfully!');
            }

            // Clear session data
            session()->forget(['oauth_state', 'oauth_organization_id']);

            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['status' => ['type' => 'success', 'message' => $message]]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Twitter OAuth callback error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'oauth_popup' => session('oauth_popup'),
            ]);
            
            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['error' => __('Failed to connect Twitter: ') . $e->getMessage()]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to connect Twitter: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Redirect to TikTok OAuth
     */
    public function redirectToTikTok()
    {
        try {
            $state = bin2hex(random_bytes(16));
            $organizationId = session()->get('current_organization');
            $isPopup = request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest';
            
            // Force re-auth if organization has no existing accounts for this platform
            // Note: TikTok doesn't support forcing account selection via OAuth parameters
            $hasExistingAccounts = SocialAccount::where('organization_id', $organizationId)
                ->where('platform', 'tiktok')
                ->whereNull('deleted_at')
                ->exists();
            $forceReauth = request()->has('reconnect') || request()->has('force') || !$hasExistingAccounts;
            
            session(['oauth_state' => $state]);
            session(['oauth_organization_id' => $organizationId]);
            session(['oauth_popup' => $isPopup]);

            $authUrl = $this->tiktokService->getAuthorizationUrl($state);
            return redirect($authUrl);
        } catch (\Exception $e) {
            Log::error('TikTok redirect error: ' . $e->getMessage());
            
            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['error' => __('Failed to connect TikTok: ') . $e->getMessage()]);
            }
            
            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to connect TikTok: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle TikTok OAuth callback
     */
    public function handleTikTokCallback(Request $request)
    {
        try {
            // Verify state
            if ($request->state !== session('oauth_state')) {
                throw new \Exception('Invalid state parameter');
            }

            if ($request->has('error')) {
                throw new \Exception('User denied authorization: ' . $request->error_description);
            }

            // Get access token (code_verifier will be retrieved from session)
            $codeVerifier = session('tiktok_code_verifier');
            $tokenData = $this->tiktokService->getAccessToken($request->code, $codeVerifier);
            $accessToken = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? 86400;
            $openId = $tokenData['open_id'];

            if (!$openId) {
                throw new \Exception('Failed to get TikTok user ID');
            }

            // Get user profile
            $profile = $this->tiktokService->getUserProfile($accessToken);

            $organizationId = session('oauth_organization_id');

            // Check if account already exists (including soft-deleted)
            $existingAccount = SocialAccount::withTrashed()
                ->where('organization_id', $organizationId)
                ->where('platform', 'tiktok')
                ->where('platform_user_id', $openId)
                ->first();

            $accountData = [
                'uuid' => $existingAccount ? $existingAccount->uuid : Str::uuid(),
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
                'platform' => 'tiktok',
                'platform_user_id' => $openId,
                'platform_username' => $profile['username'] ?? $profile['display_name'] ?? 'TikTok User',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => Carbon::now()->addSeconds($expiresIn),
                'platform_data' => [
                    'open_id' => $openId,
                    'union_id' => $profile['union_id'] ?? null,
                    'username' => $profile['username'] ?? null,
                    'display_name' => $profile['display_name'] ?? null,
                    'avatar_url' => $profile['avatar_url'] ?? null,
                ],
                'is_active' => true,
            ];

            if ($existingAccount) {
                // If account was soft-deleted, restore it first
                if ($existingAccount->trashed()) {
                    $existingAccount->restore();
                }
                $existingAccount->update($accountData);
                $message = __('TikTok account reconnected successfully!');
            } else {
                SocialAccount::create($accountData);
                $message = __('TikTok account connected successfully!');
            }

            // Clear session data
            session()->forget(['oauth_state', 'oauth_organization_id']);

            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['status' => ['type' => 'success', 'message' => $message]]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('TikTok OAuth callback error: ' . $e->getMessage());
            
            // Check if opened in popup
            if (session('oauth_popup')) {
                session()->forget('oauth_popup');
                return view('oauth-callback', ['error' => __('Failed to connect TikTok: ') . $e->getMessage()]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'error',
                'message' => __('Failed to connect TikTok: ') . $e->getMessage()
            ]);
        }
    }
}

