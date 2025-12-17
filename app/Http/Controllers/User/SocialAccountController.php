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
use Illuminate\Support\Facades\Log;
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
    public function index()
    {
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
    }

    /**
     * Redirect to Facebook OAuth
     */
    public function redirectToFacebook()
    {
        $state = bin2hex(random_bytes(16));
        $organizationId = session()->get('current_organization');
        $isPopup = request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest';
        $forceReauth = request()->has('reconnect') || request()->has('force');
        
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => $organizationId]);
        session(['oauth_popup' => $isPopup]);

        Log::info('Facebook OAuth Redirect Started', [
            'user_id' => auth()->id(),
            'organization_id' => $organizationId,
            'is_popup' => $isPopup,
            'force_reauth' => $forceReauth,
            'state' => $state,
            'session_id' => session()->getId(),
            'has_existing_accounts' => SocialAccount::where('organization_id', $organizationId)
                ->where('platform', 'facebook')
                ->count()
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
                Log::warning('Facebook OAuth: No pages found', [
                    'user_id' => auth()->id(),
                    'organization_id' => session('oauth_organization_id'),
                    'has_token' => !empty($longLivedToken)
                ]);
                
                // If in popup, return error view instead of redirecting
                if (session('oauth_popup')) {
                    session()->forget('oauth_popup');
                    return view('oauth-callback', ['error' => __('No Facebook pages found. Please create a Facebook page first.')]);
                }
                
                return redirect('/social-accounts')->with('status', [
                    'type' => 'error',
                    'message' => __('No Facebook pages found. Please create a Facebook page first.')
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
            
            // Get organization ID from multiple sources as fallback
            $organizationId = session('oauth_organization_id') 
                ?? session('current_organization') 
                ?? null;
            
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

            // Check if account already exists
            $existingAccount = SocialAccount::where('organization_id', $organizationId)
                ->where('platform', 'facebook')
                ->where('platform_user_id', $selectedPage['id'])
                ->first();

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
            
            if (!$selectedPage['id'] || !$selectedPage['name']) {
                throw new \Exception('Invalid page data received from Facebook.');
            }

            $accountData = [
                'uuid' => $uuid,
                'organization_id' => $organizationId,
                'user_id' => auth()->id(),
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
                Log::info('Facebook Save Page: Updating existing account', [
                    'account_id' => $existingAccount->id,
                    'account_uuid' => $existingAccount->uuid,
                    'old_is_active' => $existingAccount->is_active,
                    'new_is_active' => $accountData['is_active']
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
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => session()->get('current_organization')]);
        session(['oauth_popup' => request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest']);

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

            // Check if account already exists
            $existingAccount = SocialAccount::where('organization_id', $organizationId)
                ->where('platform', 'linkedin')
                ->where('platform_user_id', $personId)
                ->first();

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
                $existingAccount->update($accountData);
                $message = __('LinkedIn account reconnected successfully!');
            } else {
                SocialAccount::create($accountData);
                $message = __('LinkedIn account connected successfully!');
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
     */
    public function redirectToInstagram()
    {
        $state = bin2hex(random_bytes(16));
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => session()->get('current_organization')]);
        session(['oauth_popup' => request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest']);

        return redirect($this->instagramService->getAuthorizationUrl($state));
    }

    /**
     * Handle Instagram OAuth callback
     */
    public function handleInstagramCallback(Request $request)
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
            $tokenData = $this->instagramService->getAccessToken($request->code);
            $shortLivedToken = $tokenData['access_token'];

            // Exchange for long-lived token
            $longLivedTokenData = $this->instagramService->getLongLivedToken($shortLivedToken);
            $longLivedToken = $longLivedTokenData['access_token'];

            // Get user's Instagram accounts
            $accounts = $this->instagramService->getInstagramAccounts($longLivedToken);

            if (empty($accounts)) {
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
            Log::error('Instagram OAuth callback error: ' . $e->getMessage());
            
            // Check if opened in popup
            if (session('oauth_popup')) {
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

            // Check if account already exists
            $existingAccount = SocialAccount::where('organization_id', $organizationId)
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
                $existingAccount->update($accountData);
                $message = __('Instagram account reconnected successfully!');
            } else {
                SocialAccount::create($accountData);
                $message = __('Instagram account connected successfully!');
            }

            // Clear session data
            $isPopup = session('oauth_popup');
            session()->forget(['instagram_accounts', 'instagram_user_token', 'oauth_state', 'oauth_organization_id', 'oauth_popup']);

            // Check if opened in popup
            if ($isPopup) {
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
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => session()->get('current_organization')]);
        session(['oauth_popup' => request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest']);

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
            $tokenData = $this->twitterService->getAccessToken($request->code);
            $accessToken = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? 7200; // Default 2 hours

            // Get user profile
            $profile = $this->twitterService->getUserProfile($accessToken);
            $userId = $profile['id'] ?? null;

            if (!$userId) {
                throw new \Exception('Failed to get Twitter profile');
            }

            $organizationId = session('oauth_organization_id');

            // Check if account already exists
            $existingAccount = SocialAccount::where('organization_id', $organizationId)
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
            Log::error('Twitter OAuth callback error: ' . $e->getMessage());
            
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
        $state = bin2hex(random_bytes(16));
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => session()->get('current_organization')]);
        session(['oauth_popup' => request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest']);

        return redirect($this->tiktokService->getAuthorizationUrl($state));
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

            // Check if account already exists
            $existingAccount = SocialAccount::where('organization_id', $organizationId)
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

