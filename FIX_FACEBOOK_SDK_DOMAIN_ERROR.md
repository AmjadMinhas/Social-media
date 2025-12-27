# Fix: Facebook JavaScript SDK Unknown Host Domain Error

## Error Message
```
JSSDK unknown host domain
The domain you are hosting the Facebook Javascript SDK is not in your app's 
Javascript SDK host domain list. Please specify your host domain in the 
App Dashboard login settings.
```

## Quick Fix Steps

### Step 1: Get Your Domain

Your domain is the URL where your application is hosted. For example:
- **Local development**: `localhost` or `127.0.0.1`
- **Production**: `yourdomain.com` or `www.yourdomain.com`
- **ngrok**: `abc123.ngrok-free.app` (without https://)

### Step 2: Go to Facebook Developer Console

1. Visit: https://developers.facebook.com/
2. Log in with your Facebook account
3. Click **"My Apps"** in the top right
4. Select your app (App ID: `4137036126542590`)

### Step 3: Add Domain to App Settings

1. In your app dashboard, go to **Settings** → **Basic**
2. Scroll down to find **"App Domains"** section
3. Click **"Add Domain"** button
4. Enter your domain (without `http://` or `https://`):
   
   **For Local Development:**
   ```
   localhost
   ```
   
   **For Production:**
   ```
   yourdomain.com
   ```
   
   **For ngrok:**
   ```
   abc123.ngrok-free.app
   ```

5. Click **"Save Changes"**

### Step 4: Add JavaScript SDK Settings (Important!)

1. Still in **Settings** → **Basic**
2. Scroll down to **"Facebook Login"** section (or look for **"JavaScript SDK Settings"**)
3. Click **"Settings"** next to Facebook Login
4. In the **"Valid OAuth Redirect URIs"** section, add:
   ```
   http://localhost:8000
   https://yourdomain.com
   ```
   (Add all URLs where your app is accessible)

5. Scroll down to **"JavaScript SDK Settings"** or **"Client OAuth Settings"**
6. Find **"JavaScript SDK Allowed Domains"** or **"Allowed Domains"**
7. Add your domain(s):
   - `localhost` (for local development)
   - `yourdomain.com` (for production)
   - `abc123.ngrok-free.app` (if using ngrok)

8. Click **"Save Changes"**

### Step 5: Alternative - Facebook Login Settings

If you don't see JavaScript SDK settings in Basic settings:

1. Go to **Products** → **Facebook Login** → **Settings**
2. Under **"Client OAuth Settings"**:
   - Add your domain to **"Valid OAuth Redirect URIs"**
   - Example: `http://localhost:8000/auth/facebook/callback`
3. Under **"JavaScript SDK Settings"**:
   - Add your domain to **"Allowed Domains"**
   - Example: `localhost` or `yourdomain.com`

### Step 6: Clear Cache and Test

1. **Clear Laravel cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Clear browser cache:**
   - Press `Ctrl + Shift + Delete` (or `Cmd + Shift + Delete` on Mac)
   - Clear cached images and files
   - Or use Incognito/Private browsing mode

3. **Refresh your application page**

## Common Domains to Add

### For Local Development:
```
localhost
127.0.0.1
```

### For Production:
```
yourdomain.com
www.yourdomain.com
```

### For ngrok (if using):
```
abc123.ngrok-free.app
```

## Important Notes

1. **Don't include `http://` or `https://`** - just the domain name
2. **Don't include port numbers** in App Domains (e.g., use `localhost` not `localhost:8000`)
3. **Wait 1-2 minutes** after saving for changes to take effect
4. **If using ngrok**, the URL changes each time - you'll need to update Facebook settings each restart (unless you have a paid static domain)

## Verification

After adding the domain, you should:
1. See no more "unknown host domain" errors
2. Be able to click "Login with Facebook" button
3. See the Facebook login popup appear

## Troubleshooting

### Still seeing the error?

1. **Double-check the domain** - Make sure it matches exactly (case-sensitive)
2. **Check both places:**
   - App Domains (Settings → Basic)
   - JavaScript SDK Allowed Domains (Facebook Login → Settings)
3. **Wait a few minutes** - Facebook settings can take 1-2 minutes to propagate
4. **Clear all caches** - Laravel cache, browser cache, and try incognito mode
5. **Check your APP_URL** in `.env` file matches the domain you added

### For ngrok Users

If you're using ngrok and the URL changes:
1. Each time you restart ngrok, you get a new URL
2. You need to update Facebook settings with the new domain
3. Consider getting a paid ngrok plan for a static domain

---

**Your App ID:** `4137036126542590`  
**Go to:** https://developers.facebook.com/apps/4137036126542590/settings/basic/


