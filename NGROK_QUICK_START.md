# ngrok Quick Start Guide - Facebook OAuth Local Testing

## 🚀 Quick Setup (5 Minutes)

### For Developer (You)

#### 1. Install & Setup ngrok
```bash
# Download from https://ngrok.com/download
# Or use chocolatey: choco install ngrok

# Sign up at https://dashboard.ngrok.com/signup (free)
# Get your authtoken from dashboard

# Configure ngrok
ngrok config add-authtoken YOUR_AUTH_TOKEN
```

#### 2. Start Your App
```bash
# Terminal 1: Start Laravel
cd D:\Archive\bab-main
php artisan serve --port=8000
```

#### 3. Start ngrok Tunnel
```bash
# Terminal 2: Start ngrok
ngrok http 8000
```

**Copy the URL shown** (e.g., `https://abc123.ngrok-free.app`)

#### 4. Update .env
```env
APP_URL=https://abc123.ngrok-free.app
```

#### 5. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
```

---

### For Client (Facebook App Settings)

#### Step 1: Go to Facebook Developers
- Visit: https://developers.facebook.com/
- Login → My Apps → Select Your App

#### Step 2: Settings → Basic

**A. Add App Domain:**
- Scroll to "App Domains"
- Click "Add Domain"
- Enter: `abc123.ngrok-free.app` (NO https://)
- Save

**B. Add OAuth Redirect URI:**
- Scroll to "Valid OAuth Redirect URIs"
- Click "Add URI"
- Enter: `https://abc123.ngrok-free.app/auth/facebook/callback`
- Save

#### Step 3: Verify Credentials
- Note your **App ID** and **App Secret**
- Make sure they match your `.env`:
  ```env
  FACEBOOK_APP_ID=your_app_id
  FACEBOOK_APP_SECRET=your_app_secret
  ```

---

## ✅ Test It!

1. Visit: `https://abc123.ngrok-free.app/social-accounts`
2. Click "Connect Facebook"
3. Should work! 🎉

---

## ⚠️ Important Notes

1. **ngrok URL changes each time** (free plan)
   - You'll need to update Facebook settings each restart
   - Or get paid plan ($8/month) for static domain

2. **Keep both terminals open**
   - Terminal 1: Laravel server
   - Terminal 2: ngrok tunnel

3. **View requests in real-time:**
   - Open: http://127.0.0.1:4040 (ngrok web interface)

---

## 🔄 Daily Workflow

```bash
# 1. Start Laravel
php artisan serve --port=8000

# 2. Start ngrok (new terminal)
ngrok http 8000

# 3. Copy new ngrok URL

# 4. Update .env
APP_URL=https://new-url.ngrok-free.app

# 5. Clear cache
php artisan config:clear

# 6. Update Facebook App settings with new URL
# 7. Test!
```

---

## 🐛 Common Issues

| Issue | Solution |
|-------|----------|
| "Invalid OAuth Redirect URI" | Make sure URL in Facebook matches EXACTLY (including https://) |
| "502 Bad Gateway" | Check Laravel is running on port 8000 |
| "Session expired" | Both Laravel and ngrok must be running |
| URL not working | Clear Laravel cache after changing APP_URL |

---

## 📞 Need Help?

Check the full guide: `LOCAL_FACEBOOK_OAUTH_SETUP.md`









