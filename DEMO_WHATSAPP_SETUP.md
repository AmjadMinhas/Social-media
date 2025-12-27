# Demo WhatsApp Connection Setup Guide

This guide helps you create a **dummy WhatsApp connection** for demo/testing purposes. This will make the system think WhatsApp is connected without requiring actual Meta/Facebook credentials.

## ⚠️ Important Notes

- This is **FOR DEMO/TESTING ONLY**
- The dummy data will **NOT** actually connect to WhatsApp
- You **cannot send/receive real messages** with this dummy connection
- This is only to make the UI show WhatsApp as "connected"

## Option 1: Using Seeder (Recommended)

### Step 1: Run the Seeder

```bash
php artisan db:seed --class=DummyWhatsAppConnection
```

This will automatically add dummy WhatsApp data to the first organization in your database.

### Step 2: Verify

1. Go to **Settings** > **WhatsApp** in your application
2. You should see WhatsApp connection details displayed
3. Phone number: `+1 234 567 8900`
4. Business name: `Demo Business`

## Option 2: Using SQL Script (Direct Database Update)

### Step 1: Find Your Organization ID

Run this SQL to find your organization ID:

```sql
SELECT id, name FROM organizations;
```

### Step 2: Update Metadata

Run the SQL script based on your MySQL version:

#### For MySQL 5.7+ (Using JSON_SET):

```sql
UPDATE organizations 
SET metadata = JSON_SET(
    COALESCE(metadata, '{}'),
    '$.whatsapp',
    JSON_OBJECT(
        'is_embedded_signup', 0,
        'access_token', 'EAABwzLixnjYBO_dummy_access_token_for_demo_purposes_only',
        'app_id', '123456789012345',
        'waba_id', '123456789012345',
        'phone_number_id', '123456789012345',
        'display_phone_number', '+1 234 567 8900',
        'verified_name', 'Demo Business',
        'quality_rating', 'GREEN',
        'name_status', 'APPROVED',
        'messaging_limit_tier', 'TIER_50',
        'number_status', 'CONNECTED',
        'code_verification_status', 'VERIFIED',
        'account_review_status', 'APPROVED',
        'concurrent_mode_enabled', true,
        'business_app_enabled', true,
        'api_enabled', true,
        'multi_device_enabled', true,
        'business_profile', JSON_OBJECT(
            'about', 'This is a demo business account for testing purposes',
            'address', '123 Demo Street, Demo City, DC 12345',
            'description', 'A demo WhatsApp Business account',
            'industry', 'OTHER',
            'email', 'demo@example.com'
        )
    )
)
WHERE id = 1;  -- Change 1 to your organization ID
```

#### For MySQL 8.0+ (Using JSON_MERGE_PATCH):

You can use the more elegant JSON_MERGE_PATCH method:

```sql
UPDATE organizations 
SET metadata = JSON_MERGE_PATCH(
    COALESCE(metadata, '{}'),
    '{
        "whatsapp": {
            "is_embedded_signup": 0,
            "access_token": "EAABwzLixnjYBO_dummy_access_token_for_demo_purposes_only",
            "app_id": "123456789012345",
            "waba_id": "123456789012345",
            "phone_number_id": "123456789012345",
            "display_phone_number": "+1 234 567 8900",
            "verified_name": "Demo Business",
            "quality_rating": "GREEN",
            "name_status": "APPROVED",
            "messaging_limit_tier": "TIER_50",
            "number_status": "CONNECTED",
            "code_verification_status": "VERIFIED",
            "account_review_status": "APPROVED",
            "concurrent_mode_enabled": true,
            "business_app_enabled": true,
            "api_enabled": true,
            "multi_device_enabled": true,
            "business_profile": {
                "about": "This is a demo business account for testing purposes",
                "address": "123 Demo Street, Demo City, DC 12345",
                "description": "A demo WhatsApp Business account",
                "industry": "OTHER",
                "email": "demo@example.com"
            }
        }
    }'
)
WHERE id = 1;  -- Change 1 to your organization ID
```

### Step 3: Verify the Update

Run this query to check if the data was inserted correctly:

```sql
SELECT 
    id,
    name,
    JSON_EXTRACT(metadata, '$.whatsapp.display_phone_number') as phone,
    JSON_EXTRACT(metadata, '$.whatsapp.verified_name') as business_name,
    JSON_EXTRACT(metadata, '$.whatsapp.number_status') as status
FROM organizations 
WHERE JSON_EXTRACT(metadata, '$.whatsapp.display_phone_number') IS NOT NULL;
```

## Option 3: Using Laravel Tinker

### Step 1: Open Tinker

```bash
php artisan tinker
```

### Step 2: Run This Code

```php
$org = \App\Models\Organization::first();

$dummyData = [
    'whatsapp' => [
        'is_embedded_signup' => 0,
        'access_token' => 'EAABwzLixnjYBO_dummy_access_token_for_demo_purposes_only',
        'app_id' => '123456789012345',
        'waba_id' => '123456789012345',
        'phone_number_id' => '123456789012345',
        'display_phone_number' => '+1 234 567 8900',
        'verified_name' => 'Demo Business',
        'quality_rating' => 'GREEN',
        'name_status' => 'APPROVED',
        'messaging_limit_tier' => 'TIER_50',
        'number_status' => 'CONNECTED',
        'code_verification_status' => 'VERIFIED',
        'account_review_status' => 'APPROVED',
        'concurrent_mode_enabled' => true,
        'business_app_enabled' => true,
        'api_enabled' => true,
        'multi_device_enabled' => true,
        'business_profile' => [
            'about' => 'This is a demo business account for testing purposes',
            'address' => '123 Demo Street, Demo City, DC 12345',
            'description' => 'A demo WhatsApp Business account',
            'industry' => 'OTHER',
            'email' => 'demo@example.com'
        ]
    ]
];

$existingMetadata = $org->metadata ? json_decode($org->metadata, true) : [];
$existingMetadata['whatsapp'] = $dummyData['whatsapp'];
$org->metadata = json_encode($existingMetadata);
$org->save();

echo "✅ Dummy WhatsApp data added to: " . $org->name . "\n";
```

## What You'll See

After running any of the above methods, when you go to **Settings** > **WhatsApp**, you'll see:

- ✅ **Display name**: Demo Business
- ✅ **Connected number**: +1 234 567 8900
- ✅ **Number status**: CONNECTED
- ✅ **Account status**: APPROVED
- ✅ **Quality rating**: GREEN
- ✅ **Concurrent Mode**: Enabled
- ✅ **Multi-Device Support**: Enabled
- ✅ Business profile information

## Customizing the Dummy Data

You can customize any of the dummy values:

- **Display Phone Number**: Change `+1 234 567 8900` to any number you want
- **Business Name**: Change `Demo Business` to your preferred name
- **Business Address/Email**: Update the business_profile fields

## Removing Dummy Data

To remove the dummy WhatsApp connection:

```sql
UPDATE organizations 
SET metadata = JSON_REMOVE(metadata, '$.whatsapp')
WHERE id = 1;  -- Change to your organization ID
```

Or in Tinker:

```php
$org = \App\Models\Organization::find(1);
$metadata = json_decode($org->metadata, true);
unset($metadata['whatsapp']);
$org->metadata = json_encode($metadata);
$org->save();
```

## Troubleshooting

### Issue: Changes not showing in UI

1. **Clear cache**: `php artisan cache:clear`
2. **Refresh the page** in your browser
3. **Check browser console** for JavaScript errors

### Issue: JSON error in SQL

- Make sure you're using MySQL 5.7+ (JSON support required)
- Check that the JSON syntax is correct
- Try using the seeder method instead

### Issue: Data not saving

- Verify you have write permissions to the database
- Check that the organization ID exists
- Try the Tinker method for more detailed error messages

---

**Remember**: This is for demo purposes only. Real WhatsApp functionality requires actual Meta/Facebook API credentials.




