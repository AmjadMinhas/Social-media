-- Simple SQL to add dummy WhatsApp connection for demo
-- Replace YOUR_ORGANIZATION_ID with your actual organization ID

UPDATE organizations 
SET metadata = JSON_SET(
    IFNULL(metadata, '{}'),
    '$.whatsapp',
    CAST('{
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
    }' AS JSON)
)
WHERE id = 1;  -- Change this to your organization ID




