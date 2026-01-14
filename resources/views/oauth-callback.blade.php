<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth Callback</title>
</head>
<body>
    <script>
        (function() {
            // Use window.location.origin to match the current origin
            const targetOrigin = window.location.origin;
            let messageSent = false;
            let windowClosed = false;
            
            function sendMessageAndClose(type, message) {
                if (messageSent) return;
                messageSent = true;
                
                console.log('OAuth callback: Attempting to send message and close', { type, message, hasOpener: !!window.opener });
                
                if (window.opener && !window.opener.closed) {
                    try {
                        window.opener.postMessage({
                            type: type,
                            message: message
                        }, targetOrigin);
                        console.log('OAuth callback: Message sent to parent', { type, message });
                    } catch (e) {
                        console.error('OAuth callback: Failed to send message', e);
                    }
                }
                
                // Close window immediately - try multiple methods
                function closeWindow() {
                    if (windowClosed) return;
                    
                    try {
                        window.close();
                        windowClosed = true;
                        console.log('OAuth callback: Window closed successfully');
                        return true;
                    } catch (e) {
                        console.warn('OAuth callback: window.close() failed, trying alternatives', e);
                        
                        // Try redirecting parent
                        if (window.opener && !window.opener.closed) {
                            try {
                                window.opener.location.href = '/social-accounts';
                                console.log('OAuth callback: Redirected parent window');
                                return true;
                            } catch (e2) {
                                console.error('OAuth callback: Failed to redirect parent', e2);
                            }
                        }
                        
                        // Last resort: redirect self
                        try {
                            window.location.href = '/social-accounts';
                            console.log('OAuth callback: Redirected self');
                            return true;
                        } catch (e3) {
                            console.error('OAuth callback: All close methods failed', e3);
                            return false;
                        }
                    }
                }
                
                // Try to close immediately
                if (!closeWindow()) {
                    // If immediate close failed, try again after short delay
                    setTimeout(closeWindow, 50);
                }
            }
            
            // Process immediately on load
            @if(session('status'))
                // Success
                sendMessageAndClose('oauth_success', {!! json_encode(session('status')['message'] ?? 'Connected successfully!') !!});
            @elseif(isset($error))
                // Error - use JSON encoding to handle special characters
                sendMessageAndClose('oauth_error', {!! json_encode($error) !!});
            @else
                // Default - close popup
                sendMessageAndClose('oauth_success', 'Connection completed');
            @endif
            
            // Fallback: Force close after 500ms if nothing happened
            setTimeout(function() {
                if (!messageSent || !windowClosed) {
                    console.warn('OAuth callback: Force closing after timeout');
                    const message = @if(isset($error)) {!! json_encode($error) !!} @else 'Connection completed' @endif;
                    const type = @if(isset($error)) 'oauth_error' @else 'oauth_success' @endif;
                    sendMessageAndClose(type, message);
                }
            }, 500);
        })();
    </script>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <p>Processing connection...</p>
        <p style="color: #666; font-size: 14px;">This window will close automatically.</p>
        <p style="color: #999; font-size: 12px; margin-top: 20px;">
            If this window doesn't close automatically, <a href="/social-accounts" style="color: #0066cc;">click here</a>.
        </p>
    </div>
</body>
</html>



