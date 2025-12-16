<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth Callback</title>
</head>
<body>
    <script>
        // Use window.location.origin to match the current origin
        const targetOrigin = window.location.origin;
        
        @if(session('status'))
            // Success
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_success',
                    message: '{{ session('status')['message'] ?? 'Connected successfully!' }}'
                }, targetOrigin);
                // Close after a short delay to ensure message is sent
                setTimeout(function() {
                    window.close();
                }, 100);
            } else {
                // Not in popup, redirect normally
                window.location.href = '/social-accounts';
            }
        @elseif(isset($error))
            // Error
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_error',
                    message: '{{ $error }}'
                }, targetOrigin);
                // Close after a short delay to ensure message is sent
                setTimeout(function() {
                    window.close();
                }, 100);
            } else {
                // Not in popup, redirect with error
                window.location.href = '/social-accounts?error=' + encodeURIComponent('{{ $error }}');
            }
        @else
            // Default - close popup
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_success',
                    message: 'Connected successfully!'
                }, targetOrigin);
                // Close after a short delay to ensure message is sent
                setTimeout(function() {
                    window.close();
                }, 100);
            } else {
                window.location.href = '/social-accounts';
            }
        @endif
    </script>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <p>Processing connection...</p>
        <p style="color: #666; font-size: 14px;">This window will close automatically.</p>
    </div>
</body>
</html>



