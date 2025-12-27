# Quick Debugging Guide

## Issue 1: Media Upload Not Working

**To Debug:**
1. Open browser console (F12)
2. Click on the media upload area
3. Check console for:
   - "Media upload triggered" message
   - Any JavaScript errors

**If nothing happens:**
- Check if `mediaInput` ref is properly set
- Try clicking directly on the file input label

**Quick Fix Test:**
Open browser console and run:
```javascript
document.getElementById('media-upload').click()
```
If this works, the issue is with the click handler.

## Issue 2: Form Submission Not Working

**To Debug:**
1. Open browser console (F12)
2. Fill out the form
3. Click "Schedule Post"
4. Check console for:
   - "Form submit triggered"
   - "Submitting form to /post-scheduler..."
   - Any errors

**Check Network Tab:**
1. Open Network tab in DevTools
2. Click "Schedule Post"
3. Look for POST request to `/post-scheduler`
4. Check:
   - Request payload
   - Response status
   - Response body

**Common Issues:**
- Form validation failing (check console errors)
- CSRF token missing
- Missing required fields

## Issue 3: Facebook Messages Not Appearing

**To Debug:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Look for:
   - "UnifiedSocialInbox: Starting Facebook sync"
   - "Facebook Messenger: Fetching messages"
   - Any error messages

**Check Database:**
```sql
SELECT * FROM social_accounts WHERE platform = 'facebook' AND is_active = 1;
SELECT * FROM chats WHERE platform = 'facebook' ORDER BY created_at DESC LIMIT 10;
```

**Manual Test:**
1. Go to `/social-inbox`
2. Click "Sync" button
3. Check browser console for errors
4. Check Laravel logs immediately after

**Common Issues:**
- Facebook account not active (`is_active = 0`)
- Missing permissions (`pages_messaging`, `pages_read_mailboxes`)
- Access token expired
- No conversations on Facebook page

## Quick Test Commands

### Check Facebook Account:
```bash
php artisan tinker
>>> $account = \App\Models\SocialAccount::where('platform', 'facebook')->where('is_active', true)->first();
>>> $account->platform_data;
>>> $account->access_token ? 'Has token' : 'No token';
```

### Test Facebook API:
```bash
php artisan tinker
>>> $account = \App\Models\SocialAccount::where('platform', 'facebook')->where('is_active', true)->first();
>>> $service = new \App\Services\SocialMedia\FacebookMessengerService();
>>> $messages = $service->fetchMessages($account);
>>> count($messages);
```

### Check Logs:
```bash
tail -f storage/logs/laravel.log | grep -i "facebook\|sync\|post-scheduler"
```

## What to Check Right Now

1. **Open Browser Console** (F12)
2. **Try to upload media** - check for console messages
3. **Try to submit form** - check for console messages and network requests
4. **Check Laravel logs** - look for errors
5. **Check if Facebook account is active** in database

## If Still Not Working

Share these with me:
1. Browser console errors (screenshot or copy)
2. Network tab showing the POST request (screenshot)
3. Laravel log errors (last 50 lines)
4. Database query results for social_accounts









