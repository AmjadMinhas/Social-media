<?php

/**
 * Quick script to create dummy WhatsApp connection for demo
 * 
 * Usage: php create_dummy_whatsapp.php
 * 
 * This will add dummy WhatsApp data to the first organization in your database
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Organization;

echo "🚀 Creating dummy WhatsApp connection...\n\n";

// Get first organization
$organization = Organization::first();

if (!$organization) {
    echo "❌ No organization found. Please create an organization first.\n";
    exit(1);
}

// Dummy WhatsApp data
$dummyWhatsAppData = [
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
        'max_daily_conversation_per_phone' => null,
        'max_phone_numbers_per_business' => null,
        'number_status' => 'CONNECTED',
        'code_verification_status' => 'VERIFIED',
        'business_verification' => '',
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
            'email' => 'demo@example.com',
            'profile_picture_url' => null
        ],
        'devices' => [
            'device_demo_primary_' . time() => [
                'device_id' => 'device_demo_primary_' . time(),
                'access_token' => 'EAABwzLixnjYBO_dummy_access_token_for_demo_purposes_only',
                'phone_number_id' => '123456789012345',
                'app_id' => '123456789012345',
                'waba_id' => '123456789012345',
                'device_name' => 'Primary Device',
                'device_type' => 'api',
                'is_active' => true,
                'is_primary' => true,
                'connected_at' => date('c'),
                'last_used_at' => date('c'),
                'enable_business_app' => true,
                'enable_api' => true
            ]
        ]
    ]
];

// Get existing metadata
$existingMetadata = $organization->metadata ? json_decode($organization->metadata, true) : [];

// Merge dummy data
$existingMetadata['whatsapp'] = $dummyWhatsAppData['whatsapp'];

// Save
$organization->metadata = json_encode($existingMetadata);
$organization->save();

echo "✅ Success!\n\n";
echo "Organization: {$organization->name} (ID: {$organization->id})\n";
echo "📱 Phone Number: {$dummyWhatsAppData['whatsapp']['display_phone_number']}\n";
echo "🏢 Business Name: {$dummyWhatsAppData['whatsapp']['verified_name']}\n";
echo "📊 Status: {$dummyWhatsAppData['whatsapp']['number_status']}\n";
echo "✨ Quality Rating: {$dummyWhatsAppData['whatsapp']['quality_rating']}\n\n";
echo "🎉 Dummy WhatsApp connection created! Go to Settings > WhatsApp to see it.\n";




