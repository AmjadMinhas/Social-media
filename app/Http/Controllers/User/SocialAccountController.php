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
        $organizationId = session()->get('current_organization');

        $accounts = SocialAccount::where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->get();

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
        session(['oauth_state' => $state]);
        session(['oauth_organization_id' => session()->get('current_organization')]);
        session(['oauth_popup' => request()->has('popup') || request()->header('X-Requested-With') === 'XMLHttpRequest']);

        return redirect($this->facebookService->getAuthorizationUrl($state));
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function handleFacebookCallback(Request $request)
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
            $tokenData = $this->facebookService->getAccessToken($request->code);
            $shortLivedToken = $tokenData['access_token'];

            // Exchange for long-lived token
            $longLivedTokenData = $this->facebookService->getLongLivedToken($shortLivedToken);
            $longLivedToken = $longLivedTokenData['access_token'];

            // Get user's pages
            $pages = $this->facebookService->getUserPages($longLivedToken);

            if (empty($pages)) {
                return redirect('/social-accounts')->with('status', [
                    'type' => 'error',
                    'message' => __('No Facebook pages found. Please create a Facebook page first.')
                ]);
            }

            // Store in session to show page selection
            session(['facebook_pages' => $pages]);
            session(['facebook_user_token' => $longLivedToken]);

            // If in popup, render in popup-friendly way
            if (session('oauth_popup')) {
                return Inertia::render('User/SocialAccounts/SelectFacebookPage', [
                    'title' => __('Select Facebook Page'),
                    'pages' => $pages,
                    'isPopup' => true,
                ]);
            }

            return Inertia::render('User/SocialAccounts/SelectFacebookPage', [
                'title' => __('Select Facebook Page'),
                'pages' => $pages,
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
        $request->validate([
            'page_id' => 'required|string',
        ]);

        try {
            $pages = session('facebook_pages', []);
            $userToken = session('facebook_user_token');
            $organizationId = session('oauth_organization_id');

            $selectedPage = collect($pages)->firstWhere('id', $request->page_id);

            if (!$selectedPage) {
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

            $accountData = [
                'uuid' => $existingAccount ? $existingAccount->uuid : Str::uuid(),
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
                $existingAccount->update($accountData);
                $message = __('Facebook page reconnected successfully!');
            } else {
                SocialAccount::create($accountData);
                $message = __('Facebook page connected successfully!');
            }

            // Clear session data
            $isPopup = session('oauth_popup');
            session()->forget(['facebook_pages', 'facebook_user_token', 'oauth_state', 'oauth_organization_id', 'oauth_popup']);

            // Check if opened in popup
            if ($isPopup) {
                return view('oauth-callback', ['status' => ['type' => 'success', 'message' => $message]]);
            }

            return redirect('/social-accounts')->with('status', [
                'type' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Facebook save page error: ' . $e->getMessage());
            
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

            // Get access token
            $tokenData = $this->tiktokService->getAccessToken($request->code);
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

