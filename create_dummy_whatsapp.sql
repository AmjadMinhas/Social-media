-- SQL Script to create dummy WhatsApp connection for demo purposes
-- Run this SQL to make it appear like WhatsApp is connected

-- Replace YOUR_ORGANIZATION_ID with the actual organization ID you want to update
-- You can find organization IDs by running: SELECT id, name FROM organizations;

-- Example: Update organization with ID 1
UPDATE organizations 
SET metadata = JSON_SET(
    COALESCE(metadata, '{}'),
    '$.whatsapp.is_embedded_signup', 0,
    '$.whatsapp.access_token', 'EAABwzLixnjYBO_dummy_access_token_for_demo_purposes_only',
    '$.whatsapp.app_id', '123456789012345',
    '$.whatsapp.waba_id', '123456789012345',
    '$.whatsapp.phone_number_id', '123456789012345',
    '$.whatsapp.display_phone_number', '+1 234 567 8900',
    '$.whatsapp.verified_name', 'Demo Business',
    '$.whatsapp.quality_rating', 'GREEN',
    '$.whatsapp.name_status', 'APPROVED',
    '$.whatsapp.messaging_limit_tier', 'TIER_50',
    '$.whatsapp.number_status', 'CONNECTED',
    '$.whatsapp.code_verification_status', 'VERIFIED',
    '$.whatsapp.account_review_status', 'APPROVED',
    '$.whatsapp.concurrent_mode_enabled', true,
    '$.whatsapp.business_app_enabled', true,
    '$.whatsapp.api_enabled', true,
    '$.whatsapp.multi_device_enabled', true,
    '$.whatsapp.business_profile.about', 'This is a demo business account for testing purposes',
    '$.whatsapp.business_profile.address', '123 Demo Street, Demo City, DC 12345',
    '$.whatsapp.business_profile.description', 'A demo WhatsApp Business account',
    '$.whatsapp.business_profile.industry', 'OTHER',
    '$.whatsapp.business_profile.email', 'demo@example.com'
)
WHERE id = 1;  -- Change this to your organization ID

-- Alternative: If you prefer to use JSON_MERGE_PATCH for MySQL 8.0+
-- UPDATE organizations 
-- SET metadata = JSON_MERGE_PATCH(
--     COALESCE(metadata, '{}'),
--     '{
--         "whatsapp": {
--             "is_embedded_signup": 0,
--             "access_token": "EAABwzLixnjYBO_dummy_access_token_for_demo_purposes_only",
--             "app_id": "123456789012345",
--             "waba_id": "123456789012345",
--             "phone_number_id": "123456789012345",
--             "display_phone_number": "+1 234 567 8900",
--             "verified_name": "Demo Business",
--             "quality_rating": "GREEN",
--             "name_status": "APPROVED",
--             "messaging_limit_tier": "TIER_50",
--             "number_status": "CONNECTED",
--             "code_verification_status": "VERIFIED",
--             "account_review_status": "APPROVED",
--             "concurrent_mode_enabled": true,
--             "business_app_enabled": true,
--             "api_enabled": true,
--             "multi_device_enabled": true,
--             "business_profile": {
--                 "about": "This is a demo business account for testing purposes",
--                 "address": "123 Demo Street, Demo City, DC 12345",
--                 "description": "A demo WhatsApp Business account",
--                 "industry": "OTHER",
--                 "email": "demo@example.com"
--             }
--         }
--     }'
-- )
-- WHERE id = 1;

-- To check if it worked, run:
-- SELECT id, name, JSON_EXTRACT(metadata, '$.whatsapp.display_phone_number') as phone, 
--        JSON_EXTRACT(metadata, '$.whatsapp.verified_name') as business_name
-- FROM organizations WHERE id = 1;




