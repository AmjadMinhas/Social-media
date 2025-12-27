<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Coexistence Service
 * 
 * Manages WhatsApp Coexistence feature which allows using the same phone number
 * for both WhatsApp Business App and WhatsApp Business API (Cloud API) simultaneously
 */
class WhatsAppCoexistenceService
{
    protected $organizationId;

    /**
     * Unsupported countries for WhatsApp Coexistence
     * Based on Meta's documentation
     */
    protected $unsupportedCountries = [
        'CN', // China
        'KP', // North Korea
        'IR', // Iran
        'SY', // Syria
        'CU', // Cuba
    ];

    /**
     * Minimum required WhatsApp Business App version
     */
    protected $minAppVersion = '2.24.17';

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
    }

    /**
     * Check if coexistence is supported for the organization
     * 
     * @return array
     */
    public function checkCoexistenceSupport()
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        if (!isset($metadata['whatsapp'])) {
            return [
                'supported' => false,
                'reason' => 'WhatsApp not configured',
                'message' => 'Please setup WhatsApp integration first'
            ];
        }

        $phoneNumber = $metadata['whatsapp']['display_phone_number'] ?? null;
        if (!$phoneNumber) {
            return [
                'supported' => false,
                'reason' => 'Phone number not found',
                'message' => 'Phone number information not available'
            ];
        }

        // Extract country code from phone number
        $countryCode = $this->extractCountryCode($phoneNumber);
        
        // Check country support
        if (in_array($countryCode, $this->unsupportedCountries)) {
            return [
                'supported' => false,
                'reason' => 'country_not_supported',
                'country_code' => $countryCode,
                'message' => "WhatsApp Coexistence is not supported in your country (Country Code: {$countryCode})"
            ];
        }

        // Check if already enabled
        $isEnabled = isset($metadata['whatsapp']['concurrent_mode_enabled']) 
            && $metadata['whatsapp']['concurrent_mode_enabled'] === true;

        return [
            'supported' => true,
            'enabled' => $isEnabled,
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
            'requirements' => $this->getRequirements(),
            'message' => $isEnabled 
                ? 'WhatsApp Coexistence is already enabled' 
                : 'WhatsApp Coexistence is supported for your number'
        ];
    }

    /**
     * Get coexistence requirements
     * 
     * @return array
     */
    public function getRequirements()
    {
        return [
            'business_app_version' => [
                'required' => $this->minAppVersion,
                'description' => 'WhatsApp Business App version 2.24.17 or higher',
                'check_url' => 'https://www.whatsapp.com/download'
            ],
            'country_support' => [
                'description' => 'Your phone number country code must be supported',
                'unsupported_countries' => $this->unsupportedCountries
            ],
            'waiting_period' => [
                'description' => 'If you recently disconnected, wait 1-2 months before re-enabling',
                'note' => 'Build credibility by actively using the number during this period'
            ],
            'facebook_page_link' => [
                'description' => 'Link your WhatsApp Business account with a Facebook Page',
                'steps' => $this->getFacebookPageLinkSteps()
            ]
        ];
    }

    /**
     * Get steps to link Facebook Page
     * 
     * @return array
     */
    protected function getFacebookPageLinkSteps()
    {
        return [
            [
                'step' => 1,
                'action' => 'Open WhatsApp Business app',
                'description' => 'Launch the WhatsApp Business app on your mobile device'
            ],
            [
                'step' => 2,
                'action' => 'Navigate to Settings',
                'description' => 'On Android: Tap More options. On iPhone: Tap Settings'
            ],
            [
                'step' => 3,
                'action' => 'Go to Business tools',
                'description' => 'Tap Business tools > Facebook & Instagram'
            ],
            [
                'step' => 4,
                'action' => 'Connect Facebook Page',
                'description' => 'Tap Facebook > Continue. Enter your Facebook login information and select the Page to link'
            ],
            [
                'step' => 5,
                'action' => 'Verify connection',
                'description' => 'You\'ll see a WhatsApp button on your Facebook page when successfully linked'
            ]
        ];
    }

    /**
     * Enable coexistence mode
     * 
     * @return array
     */
    public function enableCoexistence()
    {
        $check = $this->checkCoexistenceSupport();
        
        if (!$check['supported']) {
            return [
                'success' => false,
                'message' => $check['message'],
                'reason' => $check['reason'] ?? 'unknown'
            ];
        }

        if ($check['enabled']) {
            return [
                'success' => true,
                'message' => 'Coexistence is already enabled',
                'already_enabled' => true
            ];
        }

        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        // Enable concurrent mode
        $deviceSessionService = new WhatsAppDeviceSessionService($this->organizationId);
        $deviceSessionService->enableConcurrentMode();
        
        $metadata['whatsapp']['concurrent_mode_enabled'] = true;
        $metadata['whatsapp']['business_app_enabled'] = true;
        $metadata['whatsapp']['api_enabled'] = true;
        $metadata['whatsapp']['coexistence_enabled_at'] = now()->toISOString();

        $organization->metadata = json_encode($metadata);
        $organization->save();

        Log::info('WhatsApp Coexistence enabled', [
            'organization_id' => $this->organizationId
        ]);

        return [
            'success' => true,
            'message' => 'WhatsApp Coexistence enabled successfully. You can now use both Business App and API simultaneously.',
            'setup_instructions' => $this->getSetupInstructions()
        ];
    }

    /**
     * Get setup instructions for coexistence
     * 
     * @return array
     */
    public function getSetupInstructions()
    {
        return [
            'title' => 'WhatsApp Coexistence Setup Guide',
            'sections' => [
                [
                    'title' => 'Prerequisites',
                    'items' => [
                        'WhatsApp Business App version 2.24.17 or higher installed on your mobile device',
                        'Your phone number country code must be supported',
                        'A Facebook Page linked to your WhatsApp Business account',
                        'Active WhatsApp Business API (Cloud API) integration'
                    ]
                ],
                [
                    'title' => 'Important Notes',
                    'items' => [
                        'After onboarding to Cloud API, messages sent via Business App remain free',
                        'Conversations opened via API are subject to conversation-based pricing',
                        'If you get inbound messages before onboarding, you must initiate conversations using templates only',
                        'After onboarding, you can initiate conversations with free text',
                        'All existing linked devices will be unlinked after onboarding (you can link them again)',
                        'Companion clients supported: WhatsApp Web, WhatsApp for Mac',
                        'Companion clients NOT supported: WhatsApp for Windows, WhatsApp for WearOS'
                    ]
                ],
                [
                    'title' => 'Feature Limitations',
                    'items' => [
                        'Disappearing messages will be disabled',
                        'View once messages will be disabled',
                        'Live location messages will be disabled',
                        'Broadcast lists will be disabled (existing broadcasts become read-only)',
                        'You cannot edit or delete chats sent from Business App',
                        'Chats can be retrieved for past 6 months',
                        'All messages are mirrored (messages from Business App reflect in Salesforce/API)'
                    ]
                ],
                [
                    'title' => '24-Hour Messaging Window',
                    'items' => [
                        'If you receive inbound messages BEFORE onboarding to Cloud API: You must initiate conversations using templates only, even if the conversation is open in WhatsApp Business mobile app',
                        'If you receive inbound messages AFTER onboarding to Cloud API: You can initiate conversations with free text'
                    ]
                ]
            ]
        ];
    }

    /**
     * Extract country code from phone number
     * 
     * @param string $phoneNumber
     * @return string|null
     */
    protected function extractCountryCode($phoneNumber)
    {
        // Remove + and spaces
        $phoneNumber = preg_replace('/[+\s]/', '', $phoneNumber);
        
        // Common country code patterns (first 1-3 digits)
        // This is a simplified extraction - you may want to use a phone number library
        if (preg_match('/^(\d{1,3})/', $phoneNumber, $matches)) {
            $code = $matches[1];
            
            // Map common country codes
            $countryCodeMap = [
                '1' => 'US', '44' => 'GB', '91' => 'IN', '86' => 'CN',
                '81' => 'JP', '49' => 'DE', '33' => 'FR', '39' => 'IT',
                '7' => 'RU', '82' => 'KR', '34' => 'ES', '61' => 'AU',
                '55' => 'BR', '52' => 'MX', '27' => 'ZA', '31' => 'NL',
                '46' => 'SE', '41' => 'CH', '32' => 'BE', '45' => 'DK',
                '47' => 'NO', '358' => 'FI', '351' => 'PT', '353' => 'IE',
                '48' => 'PL', '36' => 'HU', '40' => 'RO', '420' => 'CZ',
                '421' => 'SK', '385' => 'HR', '386' => 'SI', '381' => 'RS',
                '382' => 'ME', '383' => 'XK', '389' => 'MK', '355' => 'AL',
                '359' => 'BG', '30' => 'GR', '90' => 'TR', '20' => 'EG',
                '212' => 'MA', '213' => 'DZ', '216' => 'TN', '218' => 'LY',
                '249' => 'SD', '251' => 'ET', '254' => 'KE', '255' => 'TZ',
                '256' => 'UG', '257' => 'BI', '258' => 'MZ', '260' => 'ZM',
                '263' => 'ZW', '264' => 'NA', '265' => 'MW', '266' => 'LS',
                '267' => 'BW', '268' => 'SZ', '269' => 'KM', '290' => 'SH',
                '291' => 'ER', '297' => 'AW', '298' => 'FO', '299' => 'GL',
                '350' => 'GI', '352' => 'LU', '354' => 'IS', '356' => 'MT',
                '357' => 'CY', '370' => 'LT', '371' => 'LV', '372' => 'EE',
                '373' => 'MD', '374' => 'AM', '375' => 'BY', '376' => 'AD',
                '377' => 'MC', '378' => 'SM', '379' => 'VA', '380' => 'UA',
                '385' => 'HR', '386' => 'SI', '387' => 'BA', '388' => 'EU',
                '389' => 'MK', '590' => 'BL', '591' => 'BO', '592' => 'GY',
                '593' => 'EC', '594' => 'GF', '595' => 'PY', '596' => 'MQ',
                '597' => 'SR', '598' => 'UY', '599' => 'CW', '670' => 'TL',
                '672' => 'NF', '673' => 'BN', '674' => 'NR', '675' => 'PG',
                '676' => 'TO', '677' => 'SB', '678' => 'VU', '679' => 'FJ',
                '680' => 'PW', '681' => 'WF', '682' => 'CK', '683' => 'NU',
                '684' => 'AS', '685' => 'WS', '686' => 'KI', '687' => 'NC',
                '688' => 'TV', '689' => 'PF', '690' => 'TK', '691' => 'FM',
                '692' => 'MH', '850' => 'KP', '852' => 'HK', '853' => 'MO',
                '855' => 'KH', '856' => 'LA', '880' => 'BD', '886' => 'TW',
                '960' => 'MV', '961' => 'LB', '962' => 'JO', '963' => 'SY',
                '964' => 'IQ', '965' => 'KW', '966' => 'SA', '967' => 'YE',
                '968' => 'OM', '970' => 'PS', '971' => 'AE', '972' => 'IL',
                '973' => 'BH', '974' => 'QA', '975' => 'BT', '976' => 'MN',
                '977' => 'NP', '992' => 'TJ', '993' => 'TM', '994' => 'AZ',
                '995' => 'GE', '996' => 'KG', '998' => 'UZ', '1242' => 'BS',
                '1246' => 'BB', '1264' => 'AI', '1268' => 'AG', '1284' => 'VG',
                '1340' => 'VI', '1345' => 'KY', '1441' => 'BM', '1473' => 'GD',
                '1649' => 'TC', '1664' => 'MS', '1670' => 'MP', '1671' => 'GU',
                '1684' => 'AS', '1721' => 'SX', '1758' => 'LC', '1767' => 'DM',
                '1784' => 'VC', '1787' => 'PR', '1809' => 'DO', '1829' => 'DO',
                '1849' => 'DO', '1868' => 'TT', '1869' => 'KN', '1876' => 'JM',
                '1939' => 'PR', '53' => 'CU', '98' => 'IR'
            ];
            
            return $countryCodeMap[$code] ?? null;
        }
        
        return null;
    }

    /**
     * Get coexistence status information
     * 
     * @return array
     */
    public function getCoexistenceStatus()
    {
        $organization = Organization::findOrFail($this->organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        
        $isEnabled = isset($metadata['whatsapp']['concurrent_mode_enabled']) 
            && $metadata['whatsapp']['concurrent_mode_enabled'] === true;
        
        $enabledAt = $metadata['whatsapp']['coexistence_enabled_at'] ?? null;
        
        return [
            'enabled' => $isEnabled,
            'enabled_at' => $enabledAt,
            'support_check' => $this->checkCoexistenceSupport(),
            'requirements' => $this->getRequirements(),
            'setup_instructions' => $this->getSetupInstructions()
        ];
    }
}





