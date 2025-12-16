<!DOCTYPE html>
<html>
<head>
    <title>Social Accounts Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h1>Social Accounts Debug Information</h1>
    
    <div class="section">
        <h2>Session Information</h2>
        <table>
            <tr><th>Key</th><th>Value</th></tr>
            <tr><td>Session ID</td><td>{{ session()->getId() }}</td></tr>
            <tr><td>User ID</td><td>{{ auth()->id() ?? 'NOT LOGGED IN' }}</td></tr>
            <tr><td>Organization ID</td><td>{{ session('current_organization') ?? 'NOT SET' }}</td></tr>
            <tr><td>OAuth State</td><td>{{ session('oauth_state') ?? 'NOT SET' }}</td></tr>
            <tr><td>OAuth Popup</td><td>{{ session('oauth_popup') ? 'YES' : 'NO' }}</td></tr>
            <tr><td>Facebook Pages in Session</td><td>{{ !empty(session('facebook_pages')) ? count(session('facebook_pages')) . ' pages' : 'NONE' }}</td></tr>
            <tr><td>Facebook Token in Session</td><td>{{ !empty(session('facebook_user_token')) ? 'EXISTS' : 'NONE' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>Database Accounts</h2>
        @php
            $orgId = session('current_organization');
            $accounts = $orgId ? \App\Models\SocialAccount::where('organization_id', $orgId)->get() : collect();
        @endphp
        
        <p><strong>Organization ID:</strong> {{ $orgId ?? 'NOT SET' }}</p>
        <p><strong>Total Accounts:</strong> {{ $accounts->count() }}</p>
        
        @if($accounts->count() > 0)
            <table>
                <tr>
                    <th>ID</th>
                    <th>UUID</th>
                    <th>Platform</th>
                    <th>Platform User ID</th>
                    <th>Username</th>
                    <th>Active</th>
                    <th>Created</th>
                    <th>Deleted</th>
                </tr>
                @foreach($accounts as $account)
                    <tr class="{{ $account->is_active ? 'success' : 'error' }}">
                        <td>{{ $account->id }}</td>
                        <td>{{ $account->uuid }}</td>
                        <td>{{ $account->platform }}</td>
                        <td>{{ $account->platform_user_id }}</td>
                        <td>{{ $account->platform_username }}</td>
                        <td>{{ $account->is_active ? 'YES' : 'NO' }}</td>
                        <td>{{ $account->created_at }}</td>
                        <td>{{ $account->deleted_at ?? 'NO' }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="warning">No accounts found in database for this organization.</p>
        @endif
    </div>

    <div class="section">
        <h2>Facebook Accounts (Specific)</h2>
        @php
            $fbAccounts = $orgId ? \App\Models\SocialAccount::where('organization_id', $orgId)
                ->where('platform', 'facebook')
                ->whereNull('deleted_at')
                ->get() : collect();
        @endphp
        
        <p><strong>Active Facebook Accounts:</strong> {{ $fbAccounts->where('is_active', true)->count() }}</p>
        <p><strong>Total Facebook Accounts:</strong> {{ $fbAccounts->count() }}</p>
        
        @if($fbAccounts->count() > 0)
            <table>
                <tr>
                    <th>ID</th>
                    <th>UUID</th>
                    <th>Page ID</th>
                    <th>Page Name</th>
                    <th>Active</th>
                    <th>Token Expires</th>
                </tr>
                @foreach($fbAccounts as $account)
                    <tr class="{{ $account->is_active ? 'success' : 'error' }}">
                        <td>{{ $account->id }}</td>
                        <td>{{ $account->uuid }}</td>
                        <td>{{ $account->platform_user_id }}</td>
                        <td>{{ $account->platform_username }}</td>
                        <td>{{ $account->is_active ? 'YES' : 'NO' }}</td>
                        <td>{{ $account->token_expires_at ?? 'NEVER' }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="warning">No Facebook accounts found.</p>
        @endif
    </div>

    <div class="section">
        <h2>Session Data (All)</h2>
        <pre>{{ json_encode(session()->all(), JSON_PRETTY_PRINT) }}</pre>
    </div>

    <div class="section">
        <h2>Actions</h2>
        <p><a href="/social-accounts">Go to Social Accounts Page</a></p>
        <p><a href="/social-accounts?debug=1">Refresh Debug Info</a></p>
        <p><a href="?clear_session=1">Clear OAuth Session Data</a></p>
    </div>

    @if(request('clear_session'))
        @php
            session()->forget(['oauth_state', 'oauth_organization_id', 'oauth_popup', 'facebook_pages', 'facebook_user_token']);
        @endphp
        <div class="section success">
            <p>OAuth session data cleared!</p>
        </div>
    @endif
</body>
</html>

