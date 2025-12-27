# Complete Guide: Local Facebook OAuth Testing with ngrok

## Part 1: Developer Setup (What You Need to Do)

### Step 1: Install ngrok

**Windows:**
```bash
# Option A: Using Chocolatey (if you have it)
choco install ngrok

# Option B: Download manually
# 1. Go to https://ngrok.com/download
# 2. Download Windows version
# 3. Extract ngrok.exe to a folder (e.g., C:\ngrok)
# 4. Add that folder to your PATH environment variable
```

**Mac:**
```bash
# Using Homebrew
brew install ngrok/ngrok/ngrok

# Or download from https://ngrok.com/download
```

**Linux:**
```bash
# Download and install
curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null
echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list
sudo apt update && sudo apt install ngrok
```

### Step 2: Sign Up for ngrok (Free Account)

1. Go to https://dashboard.ngrok.com/signup
2. Sign up for a free account
3. After signup, you'll get an authtoken (looks like: `2abc123def456ghi789jkl012mno345pq_6r7s8t9u0v1w2x3y4z5a6b7c8d9e0f`)
4. Copy this token

### Step 3: Configure ngrok

```bash
# Authenticate ngrok with your token
ngrok config add-authtoken YOUR_AUTH_TOKEN_HERE
```

**Verify it worked:**
```bash
ngrok version
# Should show your version and account info
```

### Step 4: Start Your Laravel Application

```bash
# Navigate to your project directory
cd D:\Archive\bab-main

# Start Laravel development server
php artisan serve --port=8000

# You should see:
# Starting Laravel development server: http://127.0.0.1:8000
```

**Keep this terminal window open!**

### Step 5: Start ngrok Tunnel

**Open a NEW terminal window** and run:

```bash
ngrok http 8000
```

**You'll see output like this:**
```
ngrok

Session Status                online
Account                       Your Name (Plan: Free)
Version                       3.x.x
Region                        United States (us)
Latency                       45ms
Web Interface                 http://127.0.0.1:4040
Forwarding                    https://abc123def456.ngrok-free.app -> http://localhost:8000

Connections                   ttl     opn     rt1     rt5     p50     p90
                              0       0       0.00    0.00    0.00    0.00
```

**Important:** Copy the `Forwarding` URL (e.g., `https://abc123def456.ngrok-free.app`)

### Step 6: Update Your Laravel Configuration

**Update `.env` file:**
```env
APP_URL=https://abc123def456.ngrok-free.app
```

**Clear Laravel cache:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Step 7: Update Facebook App Settings

**See Part 2 below for Facebook App configuration**

### Step 8: Test the Connection

1. Visit: `https://abc123def456.ngrok-free.app/social-accounts`
2. Click "Connect Facebook"
3. Complete the OAuth flow
4. Check if account is saved

### Step 9: View ngrok Traffic (Optional but Helpful)

While ngrok is running, you can see all requests in real-time:
- Open: http://127.0.0.1:4040
- This shows all HTTP requests, responses, and timing

---

## Part 2: Facebook App Configuration (What Client Needs to Do)

### Step 1: Access Facebook App Settings

1. Go to https://developers.facebook.com/
2. Log in with the Facebook account that owns the app
3. Click on **"My Apps"** → Select your app (or create a new one)

### Step 2: Add ngrok Domain to App Domains

1. Go to **Settings** → **Basic**
2. Scroll down to **"App Domains"**
3. Click **"Add Domain"**
4. Enter your ngrok domain (without https://):
   ```
   abc123def456.ngrok-free.app
   ```
5. Click **"Save Changes"**

### Step 3: Add OAuth Redirect URI

1. Still in **Settings** → **Basic**
2. Scroll down to **"Valid OAuth Redirect URIs"**
3. Click **"Add URI"**
4. Enter your callback URL:
   ```
   https://abc123def456.ngrok-free.app/auth/facebook/callback
   ```
5. Click **"Save Changes"**

### Step 4: Verify App ID and Secret

1. In **Settings** → **Basic**, note:
   - **App ID** (you'll need this)
   - **App Secret** (click "Show" to reveal it)

2. Make sure these match your `.env` file:
   ```env
   FACEBOOK_APP_ID=your_app_id_here
   FACEBOOK_APP_SECRET=your_app_secret_here
   ```

### Step 5: Check App Status

1. Go to **App Review** → **Permissions and Features**
2. Make sure these permissions are approved (or in development mode):
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`
   - `pages_manage_engagement`

**Note:** If your app is in Development Mode, only you (and added test users) can use it.

### Step 6: Test Mode vs Production

**Development Mode (Recommended for Testing):**
- Only you and test users can connect
- No app review needed
- Perfect for local testing

**Production Mode:**
- Requires Facebook App Review
- Anyone can use it
- Only use after testing is complete

---

## Important Notes

### ⚠️ ngrok URL Changes Every Time

**Problem:** Each time you restart ngrok, you get a NEW URL (unless you have a paid plan).

**Solution:**
1. **Free Plan:** You'll need to update Facebook settings each time
2. **Paid Plan ($8/month):** You can get a static domain like `myapp.ngrok.io`

### 🔄 Workflow for Each Testing Session

1. Start Laravel: `php artisan serve --port=8000`
2. Start ngrok: `ngrok http 8000`
3. Copy the new ngrok URL
4. Update `.env`: `APP_URL=https://new-url.ngrok-free.app`
5. Clear cache: `php artisan config:clear`
6. Update Facebook App:
   - App Domains: `new-url.ngrok-free.app`
   - OAuth Redirect URI: `https://new-url.ngrok-free.app/auth/facebook/callback`
7. Test!

### 🐛 Troubleshooting

**Issue: "Invalid OAuth Redirect URI"**
- Make sure the URL in Facebook matches EXACTLY (including https://)
- Clear Laravel cache after changing APP_URL
- Wait 1-2 minutes for Facebook to update

**Issue: "App Domain not verified"**
- Make sure you added the domain (without https://) in App Domains
- Don't include the path, just the domain

**Issue: ngrok shows "502 Bad Gateway"**
- Make sure Laravel is running on port 8000
- Check that ngrok is forwarding to the correct port

**Issue: "Session expired" errors**
- Make sure both Laravel and ngrok are running
- Don't close the terminal windows

### 📝 Quick Reference Commands

```bash
# Start Laravel
php artisan serve --port=8000

# Start ngrok (in separate terminal)
ngrok http 8000

# Clear Laravel cache
php artisan config:clear
php artisan cache:clear

# Check ngrok status
ngrok http 8000 --log=stdout
```

### 🎯 Testing Checklist

- [ ] ngrok is running and showing forwarding URL
- [ ] Laravel is running on port 8000
- [ ] `.env` has correct `APP_URL` with ngrok URL
- [ ] Laravel cache is cleared
- [ ] Facebook App has ngrok domain in App Domains
- [ ] Facebook App has callback URL in OAuth Redirect URIs
- [ ] Facebook App ID and Secret match `.env` file
- [ ] Can access `https://your-ngrok-url.ngrok-free.app/social-accounts`
- [ ] Click "Connect Facebook" and OAuth flow works

---

## Alternative: Use ngrok Static Domain (Paid)

If you want a permanent URL that doesn't change:

1. Sign up for ngrok paid plan ($8/month)
2. Reserve a static domain: `myapp.ngrok.io`
3. Use this domain in Facebook settings
4. No need to update settings each time!

**Command:**
```bash
ngrok http 8000 --domain=myapp.ngrok.io
```

---

## Summary

**Developer Side:**
1. Install ngrok
2. Get authtoken and configure
3. Start Laravel on port 8000
4. Start ngrok tunnel
5. Update `.env` with ngrok URL
6. Clear cache

**Facebook App Side:**
1. Add ngrok domain to App Domains
2. Add callback URL to OAuth Redirect URIs
3. Verify App ID and Secret match `.env`

That's it! You can now test Facebook OAuth locally without deploying! 🎉









