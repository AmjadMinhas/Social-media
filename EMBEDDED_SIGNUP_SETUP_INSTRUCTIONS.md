# How to Enable Embedded Signup (Facebook Login) for WhatsApp

## Issue: "Embedded Signup is not available"

If you're seeing this message, it means one or more of the following requirements are not met:

1. ✅ **Embedded Signup module/addon must be enabled**
2. ✅ **WhatsApp Client ID (App ID) must be configured**
3. ✅ **WhatsApp Config ID must be configured**

## Step-by-Step Setup Instructions

### Step 1: Enable Embedded Signup Module

1. **Access Admin Panel**: Go to your admin dashboard
2. **Navigate to Addons**: Go to **Addons** or **Modules** section
3. **Find "Embedded Signup"**: Look for the "Embedded Signup" addon in the list
4. **Activate the Module**: 
   - Make sure the addon status is **Active** (`status = 1`)
   - Make sure the addon is **Enabled** (`is_active = 1`)
   - If not enabled, click to enable/activate it

### Step 2: Configure Embedded Signup Settings

You need to configure the following settings in the admin panel:

1. **Navigate to Embedded Signup Settings**:
   - Go to **Admin** > **Addons** > **Embedded Signup** (or similar)
   - Or go to the addon configuration page

2. **Enter Required Credentials**:
   - **App ID** (`whatsapp_client_id`): Your Facebook App ID
   - **Client Secret** (`whatsapp_client_secret`): Your Facebook App Secret
   - **Config ID** (`whatsapp_config_id`): Your WhatsApp Embedded Signup Configuration ID
   - **Access Token** (`whatsapp_access_token`): Your Facebook Access Token

### Step 3: Get Credentials from Meta/Facebook

#### Get App ID and Client Secret:

1. Go to [Facebook Developers Console](https://developers.facebook.com/)
2. Select your app (or create a new one)
3. Go to **Settings** > **Basic**
4. Copy:
   - **App ID** → This is your `whatsapp_client_id`
   - **App Secret** → This is your `whatsapp_client_secret`

#### Create WhatsApp Embedded Signup Configuration:

1. In Facebook Developers Console, go to **WhatsApp** > **Getting Started**
2. Or go to **WhatsApp** > **Embedded Signup**
3. Create a new Embedded Signup configuration
4. Copy the **Configuration ID** → This is your `whatsapp_config_id`

#### Get Access Token:

1. Go to **WhatsApp** > **API Setup** in Facebook Developers Console
2. Generate a System User Access Token
3. Copy the token → This is your `whatsapp_access_token`

### Step 4: Save Settings

1. Enter all the credentials in the admin addon settings page
2. Click **Save** or **Update**
3. Make sure the settings are saved in the database

### Step 5: Verify Settings in Database

The settings should be stored in the `settings` table with these keys:
- `whatsapp_client_id` = Your App ID
- `whatsapp_config_id` = Your Config ID
- `is_embedded_signup_active` = 1 (should be set to 1)

### Step 6: Check Subscription Plan

Make sure your subscription plan includes the "Embedded Signup" addon:

1. Go to **Subscriptions** or **Plans**
2. Edit your current plan
3. In the plan metadata, ensure:
   ```json
   {
     "addons": {
       "Embedded Signup": true
     }
   }
   ```

### Step 7: Refresh and Test

1. **Clear Cache** (if applicable):
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

2. **Refresh the WhatsApp Settings Page**:
   - Go to **Settings** > **WhatsApp**
   - You should now see the Facebook Login button instead of the manual setup form

3. **Test the Connection**:
   - Click "Login with Facebook"
   - The Meta popup should open
   - Follow the flow to connect your WhatsApp Business Account

## Quick Database Check

Run this query to check if settings are configured:

```sql
SELECT * FROM settings 
WHERE `key` IN ('whatsapp_client_id', 'whatsapp_config_id', 'is_embedded_signup_active');
```

Expected results:
- `whatsapp_client_id` should have a value (your App ID)
- `whatsapp_config_id` should have a value (your Config ID)
- `is_embedded_signup_active` should be '1'

## Troubleshooting

### Issue: Module is enabled but still not working

**Check 1**: Verify the addon is active:
```sql
SELECT * FROM addons WHERE name = 'Embedded Signup';
```
- `status` should be `1`
- `is_active` should be `1`

**Check 2**: Verify subscription plan includes the addon:
```sql
-- Check your subscription plan metadata
SELECT sp.metadata FROM subscription_plans sp
JOIN subscriptions s ON s.plan_id = sp.id
WHERE s.organization_id = YOUR_ORG_ID;
```
- The metadata should include `"Embedded Signup": true` in the addons section

**Check 3**: Verify settings are present:
```sql
SELECT * FROM settings 
WHERE `key` IN ('whatsapp_client_id', 'whatsapp_config_id');
```
- Both should have non-empty values

### Issue: Settings are configured but button doesn't appear

1. **Clear browser cache** and refresh
2. **Check browser console** for JavaScript errors
3. **Verify App ID and Config ID** are not empty/null in the database
4. **Check that the component receives the props** correctly

### Issue: Facebook Login popup doesn't open

1. **Check browser console** for errors
2. **Verify Facebook SDK** is loading correctly
3. **Check App ID** is valid in Facebook Developers Console
4. **Ensure Config ID** is correct and the configuration exists in Meta

## Alternative: Enable Without Module Check

If you want to enable Embedded Signup without the module check (not recommended for production), you can modify the controller to always show it when credentials are present:

```php
// In SettingController.php, change:
$embeddedSignupActive = $isModuleEnabled && !empty($appId) && !empty($configId);

// To:
$embeddedSignupActive = !empty($appId) && !empty($configId);
```

However, it's recommended to properly configure the module and settings as described above.

---

**Note**: Embedded Signup requires proper configuration in both your application and Meta/Facebook Developer Console. Make sure all credentials are correctly set up before testing.




