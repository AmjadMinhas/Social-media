<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreWhatsappSettings;
use App\Helpers\CustomHelper;
use App\Http\Requests\StoreWhatsappProfile;
use App\Models\Addon;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Template;
use App\Services\ContactFieldService;
use App\Services\WhatsappService;
use App\Services\WhatsAppDeviceSessionService;
use App\Services\WhatsAppCoexistenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Validator;

class SettingController extends BaseController
{
    public function __construct(ContactFieldService $contactFieldService)
    {
        $this->contactFieldService = $contactFieldService;
    }

    public function index(Request $request, $display = null){
        if ($request->isMethod('get')) {
            $organizationId = session()->get('current_organization');
            $data['title'] = __('Settings');
            $data['settings'] = Organization::where('id', $organizationId)->first();
            $data['timezones'] = config('formats.timezones');
            $data['countries'] = config('formats.countries');
            $data['sounds'] = config('sounds');
            $data['modules'] = Addon::get();
            $contactModel = new Contact;
            $data['contactGroups'] = $contactModel->getAllContactGroups($organizationId);

            return Inertia::render('User/Settings/General', $data);
        }
    }

    public function mobileView(Request $request){
        $data['title'] = __('Settings');
        $data['settings'] = Organization::where('id', session()->get('current_organization'))->first();
        return Inertia::render('User/Settings/Main', $data);
    }

    public function viewGeneralSettings(Request $request){
        $contactModel = new Contact;
        $organizationId = session()->get('current_organization');
        $data['title'] = __('Settings');
        $data['settings'] = Organization::where('id', session()->get('current_organization'))->first();
        $data['modules'] = Addon::get();
        $data['contactGroups'] = $contactModel->getAllContactGroups($organizationId);
        
        return Inertia::render('User/Settings/General', $data);
    }

    public function viewWhatsappSettings(Request $request){
        $settings = Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        $organizationId = session()->get('current_organization');
        $coexistenceService = new WhatsAppCoexistenceService($organizationId);
        $coexistenceStatus = $coexistenceService->getCoexistenceStatus();

        // Check if Embedded Signup addon is enabled in database
        $addonEnabled = \App\Models\Addon::where('name', 'Embedded Signup')
            ->where('status', 1)
            ->where('is_active', 1)
            ->exists();
        
        $appId = $settings->get('whatsapp_client_id', null);
        $configId = $settings->get('whatsapp_config_id', null);
        
        // Show embedded signup if addon is enabled AND credentials are configured
        $embeddedSignupActive = $addonEnabled && !empty($appId) && !empty($configId);

        $data = [
            'embeddedSignupActive' => $embeddedSignupActive ? 1 : 0,
            'graphAPIVersion' => config('graph.api_version'),
            'appId' => $appId,
            'configId' => $configId,
            'settings' => Organization::where('id', $organizationId)->first(),
            'modules' => Addon::get(),
            'title' => __('Settings'),
            'coexistenceStatus' => $coexistenceStatus,
        ];

        return Inertia::render('User/Settings/Whatsapp', $data);
    }

    public function storeWhatsappSettings(StoreWhatsappSettings $request) {
        $embeddedSignupActive = Setting::where('key', 'is_embedded_signup_active')->value('value');
        $setWebhookUrl = $embeddedSignupActive == 1 ? true : false;

        return $this->saveWhatsappSettings(
            $request->access_token,
            $request->app_id,
            $request->phone_number_id,
            $request->waba_id,
            $setWebhookUrl,
            $request->enable_concurrent_mode ?? false,
            $request->enable_multi_device ?? false,
            $request->device_name ?? 'Primary Device'
        );
    }

    public function updateToken(Request $request) {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }
        
        $organizationId = session()->get('current_organization');
        $config = Organization::findOrFail($organizationId)->metadata;
        $config = $config ? json_decode($config, true) : [];

        return $this->saveWhatsappSettings(
            $request->access_token,
            $config['whatsapp']['app_id'] ?? null,
            $config['whatsapp']['phone_number_id'] ?? null,
            $config['whatsapp']['waba_id'] ?? null
        );
    }
    
    public function refreshWhatsappData() {
        $organizationId = session()->get('current_organization');
        $config = Organization::findOrFail($organizationId)->metadata;
        $config = $config ? json_decode($config, true) : [];

        if($config['whatsapp']['is_embedded_signup'] && $config['whatsapp']['is_embedded_signup'] == 1){
            if (class_exists(\Modules\EmbeddedSignup\Services\MetaService::class)) {
                $embeddedSetup = new \Modules\EmbeddedSignup\Services\MetaService();
                $embeddedSetup->overrideWabaCallbackUrl($organizationId);
            }
        }
    
        return $this->saveWhatsappSettings(
            $config['whatsapp']['access_token'] ?? null,
            $config['whatsapp']['app_id'] ?? null,
            $config['whatsapp']['phone_number_id'] ?? null,
            $config['whatsapp']['waba_id'] ?? null
        );
    }

    public function contacts(Request $request){
        if ($request->isMethod('get')) {
            $contactFieldService = new ContactFieldService(session()->get('current_organization'));
            $settings = Organization::where('id', session()->get('current_organization'))->first();

            return Inertia::render('User/Settings/Contact', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } else if($request->isMethod('post')) {
            $currentOrganizationId = session()->get('current_organization');
            $organizationConfig = Organization::where('id', $currentOrganizationId)->first();
    
            $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

            $metadataArray['contacts']['location'] = $request->location;

            $updatedMetadataJson = json_encode($metadataArray);

            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Settings updated successfully')
                ]
            );
        }
    }

    public function tickets(Request $request){
        if ($request->isMethod('get')) {
            $contactFieldService = new ContactFieldService(session()->get('current_organization'));
            $settings = Organization::where('id', session()->get('current_organization'))->first();

            return Inertia::render('User/Settings/Ticket', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } else if($request->isMethod('post')) {
            $currentOrganizationId = session()->get('current_organization');
            $organizationConfig = Organization::where('id', $currentOrganizationId)->first();
    
            $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

            $metadataArray['tickets']['active'] = $request->active;
            $metadataArray['tickets']['auto_assignment'] = $request->auto_assignment;
            $metadataArray['tickets']['reassign_reopened_chats'] = $request->reassign_reopened_chats;
            $metadataArray['tickets']['allow_agents_to_view_all_chats'] = $request->allow_agents_to_view_all_chats;

            $updatedMetadataJson = json_encode($metadataArray);

            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            /*return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Settings updated successfully')
                ]
            );*/
        }
    }

    public function automation(Request $request){
        if ($request->isMethod('get')) {
            $settings = Organization::where('id', session()->get('current_organization'))->first();

            return Inertia::render('User/Settings/Automation', [
                'title' => __('Settings'),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } else if($request->isMethod('post')) {
            $currentOrganizationId = session()->get('current_organization');
            $organizationConfig = Organization::where('id', $currentOrganizationId)->first();
    
            $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];
            $metadataArray['automation']['response_sequence'] = $request->response_sequence;

            $updatedMetadataJson = json_encode($metadataArray);
            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            /*return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Settings updated successfully')
                ]
            );*/
        }
    }

    public function whatsappBusinessProfileUpdate(StoreWhatsappProfile $request){
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $config = Organization::where('id', $organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        if(isset($config['whatsapp'])){
            $accessToken = $config['whatsapp']['access_token'] ?? null;
            $apiVersion = config('graph.api_version');
            $appId = $config['whatsapp']['app_id'] ?? null;
            $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
            $wabaId = $config['whatsapp']['waba_id'] ?? null;

            $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);
            
            $response = $whatsappService->updateBusinessProfile($request);

            if($response->success === true){
                return back()->with(
                    'status', [
                        'type' => 'success', 
                        'message' => __('Your whatsapp business profile has been changed successfully!')
                    ]
                );
            } else {
                return back()->with(
                    'status', [
                        'type' => 'error', 
                        'message' => __('Something went wrong! Your business profile could not be updated!')
                    ]
                );
            }
        }

        return back()->with(
            'status', [
                'type' => 'error', 
                'message' => __('Setup your whatsapp integration first!')
            ]
        );
    }

    public function deleteWhatsappIntegration(Request $request){
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $embeddedSignupActive = Setting::where('key', 'is_embedded_signup_active')->value('value');
        $organizationId = session()->get('current_organization');
        $organizationConfig = Organization::where('id', $organizationId)->first();
        $config = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];

        if(isset($config['whatsapp'])){
            if($embeddedSignupActive == 1){
                //Unsubscribe webhook
                $organizationId = session()->get('current_organization');
                $apiVersion = config('graph.api_version');

                $accessToken = $config['whatsapp']['access_token'] ?? null;
                $appId = $config['whatsapp']['app_id'] ?? null;
                $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
                $wabaId = $config['whatsapp']['waba_id'] ?? null;
            
                $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);
                $unsubscribe = $whatsappService->unSubscribeToWaba();
            }
            
            //Delete whatsapp settings
            if (isset($config['whatsapp'])) {
                unset($config['whatsapp']);
            }

            $updatedMetadataJson = json_encode($config);
            $organizationConfig->metadata = $updatedMetadataJson;
            $organizationConfig->save();

            //Delete templates
            $templates = Template::where('organization_id', $organizationId)->get();
            foreach ($templates as $template) {
                $template->deleted_at = now();
                $template->save();
            }

            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Your integration has been removed successfully!')
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error', 
                'message' => __('Setup your whatsapp integration first!')
            ]
        );
    }

    private function saveWhatsappSettings($accessToken, $appId, $phoneNumberId, $wabaId, $subscribeToWebhook = false, $enableConcurrentMode = false, $enableMultiDevice = false, $deviceName = 'Primary Device') {
        $organizationId = session()->get('current_organization');
        $apiVersion = config('graph.api_version');
    
        $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $organizationId);

        $phoneNumberResponse = $whatsappService->getPhoneNumberId($accessToken, $wabaId);
        
        if(!$phoneNumberResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => $phoneNumberResponse->data->error->message
                ]
            );
        }

        //Get Phone Number Status
        $phoneNumberStatusResponse = $whatsappService->getPhoneNumberStatus($accessToken, $phoneNumberResponse->data->id); 
        
        if(!$phoneNumberStatusResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => $phoneNumberStatusResponse->data->error->message
                ]
            );
        }

        //Get Account Review Status
        $accountReviewStatusResponse = $whatsappService->getAccountReviewStatus($accessToken, $wabaId);
        
        if(!$accountReviewStatusResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => $accountReviewStatusResponse->data->error->message
                ]
            );
        }

        //Get business profile
        $businessProfileResponse = $whatsappService->getBusinessProfile();  
        
        if(!$businessProfileResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => $businessProfileResponse->data->error->message
                ]
            );
        }

        $organizationConfig = Organization::where('id', $organizationId)->first();
        
        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];
        $metadataArray['whatsapp']['is_embedded_signup'] = $metadataArray['whatsapp']['is_embedded_signup'] ?? 0;
        $metadataArray['whatsapp']['access_token'] = $accessToken;
        $metadataArray['whatsapp']['app_id'] = $appId;
        $metadataArray['whatsapp']['waba_id'] = $wabaId;
        $metadataArray['whatsapp']['phone_number_id'] = $phoneNumberResponse->data->id;
        $metadataArray['whatsapp']['display_phone_number'] = $phoneNumberResponse->data->display_phone_number;
        $metadataArray['whatsapp']['verified_name'] = $phoneNumberResponse->data->verified_name;
        $metadataArray['whatsapp']['quality_rating'] = $phoneNumberResponse->data->quality_rating;
        $metadataArray['whatsapp']['name_status'] = $phoneNumberResponse->data->name_status;
        $metadataArray['whatsapp']['messaging_limit_tier'] = $phoneNumberResponse->data->messaging_limit_tier ?? NULL;
        $metadataArray['whatsapp']['max_daily_conversation_per_phone'] = NULL;
        $metadataArray['whatsapp']['max_phone_numbers_per_business'] = NULL;
        $metadataArray['whatsapp']['number_status'] = $phoneNumberStatusResponse->data->status;
        $metadataArray['whatsapp']['code_verification_status'] = $phoneNumberStatusResponse->data->code_verification_status;
        $metadataArray['whatsapp']['business_verification'] = '';
        $metadataArray['whatsapp']['account_review_status'] = $accountReviewStatusResponse->data->account_review_status;
        $metadataArray['whatsapp']['business_profile']['about'] = $businessProfileResponse->data->about ?? NULL;
        $metadataArray['whatsapp']['business_profile']['address'] = $businessProfileResponse->data->address ?? NULL;
        $metadataArray['whatsapp']['business_profile']['description'] = $businessProfileResponse->data->description ?? NULL;
        $metadataArray['whatsapp']['business_profile']['industry'] = $businessProfileResponse->data->vertical ?? NULL;
        $metadataArray['whatsapp']['business_profile']['email'] = $businessProfileResponse->data->email ?? NULL;

        // Register device session for multi-device support
        $deviceSessionService = new WhatsAppDeviceSessionService($organizationId);
        
        // Check if this is a new connection or update
        $existingDevices = $deviceSessionService->getDeviceSessions();
        $isNewConnection = empty($existingDevices);
        
        // Register/update device session
        $deviceSessionService->registerDeviceSession([
            'access_token' => $accessToken,
            'phone_number_id' => $phoneNumberResponse->data->id,
            'app_id' => $appId,
            'waba_id' => $wabaId,
            'device_name' => $deviceName,
            'device_type' => 'api',
            'is_primary' => $isNewConnection,
            'enable_business_app' => $enableConcurrentMode,
            'enable_api' => true,
        ]);

        // Enable concurrent mode if requested
        if ($enableConcurrentMode) {
            $deviceSessionService->enableConcurrentMode();
            $metadataArray['whatsapp']['concurrent_mode_enabled'] = true;
            $metadataArray['whatsapp']['business_app_enabled'] = true;
            $metadataArray['whatsapp']['api_enabled'] = true;
        }

        // Enable multi-device support
        if ($enableMultiDevice) {
            $metadataArray['whatsapp']['multi_device_enabled'] = true;
        }

        $updatedMetadataJson = json_encode($metadataArray);
        $organizationConfig->metadata = $updatedMetadataJson;

        if($organizationConfig->save()){
            $whatsappService->syncTemplates($accessToken, $wabaId);

            $message = __('Whatsapp settings updated successfully');
            if ($enableConcurrentMode) {
                $message .= ' ' . __('Concurrent mode (Business App + API) enabled.');
            }
            if ($enableMultiDevice) {
                $message .= ' ' . __('Multi-device support enabled.');
            }

            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => $message
                ]
            );
        } else {
            return back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('Something went wrong. Refresh the page and try again')
                ]
            );
        }
    }

    /**
     * Get WhatsApp device sessions
     */
    public function getWhatsAppDeviceSessions(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $deviceSessionService = new WhatsAppDeviceSessionService($organizationId);
        
        $devices = $deviceSessionService->getDeviceSessions();
        $concurrentModeEnabled = $deviceSessionService->isConcurrentModeEnabled();
        
        return response()->json([
            'devices' => $devices,
            'concurrent_mode_enabled' => $concurrentModeEnabled,
            'multi_device_enabled' => true, // Always enabled with this implementation
        ]);
    }

    /**
     * Add a new device session
     */
    public function addWhatsAppDeviceSession(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $deviceSessionService = new WhatsAppDeviceSessionService($organizationId);

        $validated = $request->validate([
            'access_token' => 'required|string',
            'phone_number_id' => 'required|string',
            'app_id' => 'nullable|string',
            'waba_id' => 'nullable|string',
            'device_name' => 'required|string|max:255',
            'device_type' => 'nullable|in:api,business_app',
            'enable_business_app' => 'nullable|boolean',
            'enable_api' => 'nullable|boolean',
        ]);

        $result = $deviceSessionService->registerDeviceSession($validated);

        if ($result) {
            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Device session added successfully')
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error', 
                'message' => __('Failed to add device session')
            ]
        );
    }

    /**
     * Remove a device session
     */
    public function removeWhatsAppDeviceSession(Request $request, $deviceId)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $deviceSessionService = new WhatsAppDeviceSessionService($organizationId);

        $result = $deviceSessionService->removeDeviceSession($deviceId);

        if ($result) {
            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Device session removed successfully')
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error', 
                'message' => __('Failed to remove device session')
            ]
        );
    }

    /**
     * Toggle concurrent mode
     */
    public function toggleConcurrentMode(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $deviceSessionService = new WhatsAppDeviceSessionService($organizationId);

        $enabled = $request->input('enabled', false);

        if ($enabled) {
            $deviceSessionService->enableConcurrentMode();
            $message = __('Concurrent mode enabled. Business app and API can now be used simultaneously.');
        } else {
            $deviceSessionService->disableConcurrentMode();
            $message = __('Concurrent mode disabled.');
        }

        return back()->with(
            'status', [
                'type' => 'success', 
                'message' => $message
            ]
        );
    }

    /**
     * Check WhatsApp Coexistence support
     */
    public function checkCoexistenceSupport(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $coexistenceService = new WhatsAppCoexistenceService($organizationId);
        
        $status = $coexistenceService->checkCoexistenceSupport();
        
        return response()->json($status);
    }

    /**
     * Enable WhatsApp Coexistence
     */
    public function enableCoexistence(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $organizationId = session()->get('current_organization');
        $coexistenceService = new WhatsAppCoexistenceService($organizationId);
        
        $result = $coexistenceService->enableCoexistence();
        
        if ($result['success']) {
            return back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => $result['message']
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error', 
                'message' => $result['message']
            ]
        );
    }

    /**
     * Get coexistence status and instructions
     */
    public function getCoexistenceStatus(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $coexistenceService = new WhatsAppCoexistenceService($organizationId);
        
        $status = $coexistenceService->getCoexistenceStatus();
        
        return response()->json($status);
    }

    protected function abortIfDemo(){
        $organizationId = session()->get('current_organization');

        if (app()->environment('demo') && $organizationId == 1) {
            return back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('You cannot perform this action using the demo account. To test this feature, please create your own account.')
                ]
            );
        }

        return null;
    }
}