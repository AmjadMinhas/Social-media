<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class DummyWhatsAppConnection extends Seeder
{
    /**
     * Run the database seeds.
     * This creates dummy WhatsApp connection data for demo purposes
     */
    public function run()
    {
        // Get the first organization or create a dummy one
        $organization = Organization::first();
        
        if (!$organization) {
            $this->command->warn('No organization found. Please create an organization first.');
            return;
        }

        // Create dummy WhatsApp metadata
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
                        'connected_at' => now()->toISOString(),
                        'last_used_at' => now()->toISOString(),
                        'enable_business_app' => true,
                        'enable_api' => true
                    ]
                ]
            ]
        ];

        // Get existing metadata or create new
        $existingMetadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        // Merge dummy WhatsApp data with existing metadata
        $existingMetadata['whatsapp'] = $dummyWhatsAppData['whatsapp'];
        
        // Update organization metadata
        $organization->metadata = json_encode($existingMetadata);
        $organization->save();

        $this->command->info("✅ Dummy WhatsApp connection data added to organization: {$organization->name} (ID: {$organization->id})");
        $this->command->info("📱 Display Phone Number: {$dummyWhatsAppData['whatsapp']['display_phone_number']}");
        $this->command->info("🏢 Verified Name: {$dummyWhatsAppData['whatsapp']['verified_name']}");
        $this->command->info("📊 Status: {$dummyWhatsAppData['whatsapp']['number_status']}");
    }
}




