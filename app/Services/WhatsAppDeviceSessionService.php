<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Device Session Service
 * 
 * Manages multiple device sessions for WhatsApp Business API
 * allowing concurrent business app and API usage on the same number
 */
class WhatsAppDeviceSessionService
{
    protected $organizationId;

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
    }

    /**
     * Register a new device session
     * 
     * @param array $sessionData Session data including device_id, access_token, etc.
     * @return bool
     */
    public function registerDeviceSession($sessionData)
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        // Initialize devices array if not exists
        if (!isset($metadata['whatsapp']['devices'])) {
            $metadata['whatsapp']['devices'] = [];
        }

        // Generate device ID if not provided
        $deviceId = $sessionData['device_id'] ?? 'device_' . uniqid() . '_' . time();
        
        // Store device session
        $metadata['whatsapp']['devices'][$deviceId] = [
            'device_id' => $deviceId,
            'access_token' => $sessionData['access_token'] ?? null,
            'phone_number_id' => $sessionData['phone_number_id'] ?? null,
            'app_id' => $sessionData['app_id'] ?? null,
            'waba_id' => $sessionData['waba_id'] ?? null,
            'device_name' => $sessionData['device_name'] ?? 'Primary Device',
            'device_type' => $sessionData['device_type'] ?? 'api', // 'api' or 'business_app'
            'is_active' => true,
            'is_primary' => $sessionData['is_primary'] ?? (count($metadata['whatsapp']['devices']) === 0),
            'connected_at' => now()->toISOString(),
            'last_used_at' => now()->toISOString(),
            'enable_business_app' => $sessionData['enable_business_app'] ?? false,
            'enable_api' => $sessionData['enable_api'] ?? true,
        ];

        // If this is primary, unset others as primary
        if ($metadata['whatsapp']['devices'][$deviceId]['is_primary']) {
            foreach ($metadata['whatsapp']['devices'] as $key => &$device) {
                if ($key !== $deviceId) {
                    $device['is_primary'] = false;
                }
            }
        }

        // Enable concurrent mode if requested
        if (isset($sessionData['enable_concurrent_mode']) && $sessionData['enable_concurrent_mode']) {
            $metadata['whatsapp']['concurrent_mode_enabled'] = true;
            $metadata['whatsapp']['business_app_enabled'] = true;
            $metadata['whatsapp']['api_enabled'] = true;
        }

        $organization->metadata = json_encode($metadata);
        $organization->save();

        Log::info('WhatsApp device session registered', [
            'organization_id' => $this->organizationId,
            'device_id' => $deviceId,
            'device_type' => $metadata['whatsapp']['devices'][$deviceId]['device_type']
        ]);

        return true;
    }

    /**
     * Get all device sessions
     * 
     * @return array
     */
    public function getDeviceSessions()
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        return $metadata['whatsapp']['devices'] ?? [];
    }

    /**
     * Get primary device session
     * 
     * @return array|null
     */
    public function getPrimaryDeviceSession()
    {
        $devices = $this->getDeviceSessions();
        
        foreach ($devices as $device) {
            if (isset($device['is_primary']) && $device['is_primary']) {
                return $device;
            }
        }

        // Return first device if no primary found
        return !empty($devices) ? reset($devices) : null;
    }

    /**
     * Get active device sessions
     * 
     * @return array
     */
    public function getActiveDeviceSessions()
    {
        $devices = $this->getDeviceSessions();
        
        return array_filter($devices, function($device) {
            return isset($device['is_active']) && $device['is_active'] === true;
        });
    }

    /**
     * Update device session
     * 
     * @param string $deviceId
     * @param array $updates
     * @return bool
     */
    public function updateDeviceSession($deviceId, $updates)
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        if (!isset($metadata['whatsapp']['devices'][$deviceId])) {
            return false;
        }

        // Update device data
        foreach ($updates as $key => $value) {
            $metadata['whatsapp']['devices'][$deviceId][$key] = $value;
        }

        $metadata['whatsapp']['devices'][$deviceId]['last_used_at'] = now()->toISOString();

        $organization->metadata = json_encode($metadata);
        $organization->save();

        return true;
    }

    /**
     * Remove device session
     * 
     * @param string $deviceId
     * @return bool
     */
    public function removeDeviceSession($deviceId)
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        if (!isset($metadata['whatsapp']['devices'][$deviceId])) {
            return false;
        }

        unset($metadata['whatsapp']['devices'][$deviceId]);

        // If removed device was primary, assign first remaining device as primary
        $remainingDevices = $metadata['whatsapp']['devices'];
        if (!empty($remainingDevices)) {
            $firstDeviceKey = array_key_first($remainingDevices);
            $metadata['whatsapp']['devices'][$firstDeviceKey]['is_primary'] = true;
        }

        $organization->metadata = json_encode($metadata);
        $organization->save();

        Log::info('WhatsApp device session removed', [
            'organization_id' => $this->organizationId,
            'device_id' => $deviceId
        ]);

        return true;
    }

    /**
     * Check if concurrent mode is enabled
     * 
     * @return bool
     */
    public function isConcurrentModeEnabled()
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        return isset($metadata['whatsapp']['concurrent_mode_enabled']) 
            && $metadata['whatsapp']['concurrent_mode_enabled'] === true;
    }

    /**
     * Enable concurrent mode (business app + API simultaneously)
     * 
     * @return bool
     */
    public function enableConcurrentMode()
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        $metadata['whatsapp']['concurrent_mode_enabled'] = true;
        $metadata['whatsapp']['business_app_enabled'] = true;
        $metadata['whatsapp']['api_enabled'] = true;

        $organization->metadata = json_encode($metadata);
        $organization->save();

        Log::info('WhatsApp concurrent mode enabled', [
            'organization_id' => $this->organizationId
        ]);

        return true;
    }

    /**
     * Disable concurrent mode
     * 
     * @return bool
     */
    public function disableConcurrentMode()
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        $metadata['whatsapp']['concurrent_mode_enabled'] = false;

        $organization->metadata = json_encode($metadata);
        $organization->save();

        return true;
    }

    /**
     * Get device session by device ID
     * 
     * @param string $deviceId
     * @return array|null
     */
    public function getDeviceSession($deviceId)
    {
        $devices = $this->getDeviceSessions();
        return $devices[$deviceId] ?? null;
    }
}





