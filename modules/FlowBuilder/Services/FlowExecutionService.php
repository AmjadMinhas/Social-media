<?php

namespace Modules\FlowBuilder\Services;

use App\Helpers\CustomHelper;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\WhatsappService;
use Modules\FlowBuilder\Models\Flow;
use Modules\FlowBuilder\Models\FlowLog;
use Modules\FlowBuilder\Models\FlowMedia;
use Modules\FlowBuilder\Models\FlowUserData;

class FlowExecutionService
{
    private $whatsappService;
    private $organizationId;

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
        $this->initializeWhatsappService();
    }

    private function initializeWhatsappService()
    {
        $config = Organization::where('id', $this->organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $this->organizationId);
    }
    
    /**
     * Execute a flow for a user based on their input.
     *
     * @param $chat
     * @param boolean $isNewContact
     * @param string $message
     * @return FlowStep|null
     */
    public function executeFlow($chat, $isNewContact, $message)
    {
        if(CustomHelper::isModuleEnabled('Flow builder', $chat->organization_id)){
            // Find the current step for the user in the flow
            $flowData = FlowUserData::firstOrNew(['contact_id' => $chat->contact_id]);
            $flowId = null;

            if (!$flowData->exists) {
    $flow = null;

    // ============= Normalization & Matching =============
    $msg = strtolower(trim($message));

    function removeTashkeel($text) {
        $tashkeel = ['َ','ً','ُ','ٌ','ِ','ٍ','ْ','ّ'];
        return str_replace($tashkeel, '', $text);
    }

    function normalizeArabic($text) {
        $text = removeTashkeel($text);
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);
        return $text;
    }

    function isArabic($text) {
        return preg_match('/\p{Arabic}/u', $text);
    }

    // ===== 1. محاولة تشغيل فلو بناء على الكلمات المفتاحية =====
    if (isArabic($msg)) {
        $msg = normalizeArabic($msg);
        $words = explode(' ', $msg);

        $conditions = [];
        $bindings = [];

        $conditions[] = "keywords LIKE ?";
        $bindings[] = '%' . $msg . '%';

        foreach ($words as $word) {
            $word = normalizeArabic(trim($word));
            $conditions[] = "keywords LIKE ?";
            $bindings[] = '%' . $word . '%';
        }

        $flow = \DB::table('flows')->whereRaw(
            '( `trigger` = ? AND organization_id = ? AND status = ?) AND (' . implode(' OR ', $conditions) . ')',
            array_merge(['keywords', $chat->organization_id, 'active'], $bindings)
        )->first();
    } else {
        $words = explode(' ', $msg);

        $conditions = [];
        $bindings = [];

        $conditions[] = "FIND_IN_SET(?, REPLACE(keywords, ' ', ''))";
        $bindings[] = str_replace(' ', '', $msg);

        foreach ($words as $word) {
            $word = strtolower(trim($word));
            $conditions[] = "FIND_IN_SET(?, REPLACE(keywords, ' ', ''))";
            $bindings[] = str_replace(' ', '', $word);
        }

        $flow = \DB::table('flows')->whereRaw(
            '( `trigger` = ? AND organization_id = ? AND status = ?) AND (' . implode(' OR ', $conditions) . ')',
            array_merge(['keywords', $chat->organization_id, 'active'], $bindings)
        )->first();
    }

    // ===== 2. إذا لم يتم العثور على keyword وكان المستخدم جديد → نحاول new_contact =====
    if (!$flow && $isNewContact) {
        $flow = Flow::where('organization_id', $chat->organization_id)
            ->where('status', 'active')
            ->where('trigger', 'new_contact')
            ->first();
    }

    // ===== 3. حفظ بيانات الفلو إن وُجد =====
    if ($flow) {
        $flowId = $flow->id;
        $flowData->fill([
            'flow_id' => $flowId,
            'current_step' => 1
        ])->save();
    }
}
 else {
                $flowId = $flowData->id;
            }

            if ($flowId) {
                return $this->processFlow($chat, $isNewContact, $flowData, $chat->contact_id, strtolower(trim($message)));
            }

            return false;
        }
    }

    public function hasActiveFlow($chat){
        $activeFlow = FlowUserData::where('contact_id', $chat->contact_id)->first();

        return $activeFlow ? true : false;
    }

    public function processFlow($chat, $isNewContact, $flowData, $contactId, $message){
        $flow = Flow::find($flowData->flow_id);
    
        if (!$flow || empty($flow->metadata)) {
            return false;
        }
    
        $edgesArray = json_decode($flow->metadata, true);
        $edges = \Arr::get($edgesArray, "edges", null);
    
        // ابحث عن خطوة جديدة بناءً على نص الزر أو اللست
        $restartedStep = $this->findStepByButtonOrListTitle($edges, $message);
        if ($restartedStep !== null && $restartedStep != $flowData->current_step) {
            $flowData->current_step = $restartedStep;
            $flowData->save();
        }
    
        // بعد تعديل الخطوة الحالية (إذا تغيرت)، تابع التنفيذ بناءً على الخطوة الحالية
        $metadataArray = $this->findEdgesBySource($edges, $flowData->current_step, $message);
    
        if (empty($metadataArray)) {
            // حاول إيجاد خطوة تطابق النص في الخطوات السابقة
            $matchedStep = $this->findStepByButtonOrListTitle($edges, $message);
            if ($matchedStep) {
                $flowData->current_step = $matchedStep;
                $flowData->save();
                return $this->processFlow($chat, $isNewContact, $flowData, $contactId, $message);
            }
    
            // إذا لم توجد خطوة مطابقة، أنهِ الفلو وأعد تشغيله من البداية
            FlowUserData::where('contact_id', $contactId)->delete();
            $this->executeFlow($chat, $isNewContact, $message);
            return false;
        }
    
        $fieldsArray = \Arr::get($metadataArray, "data.metadata.fields", null);
        $type = $fieldsArray['type'] ?? null;
    
        $contact = Contact::find($contactId);
        if (!$contact) {
            return false;
        }
    
        $message = $this->replacePlaceholders($contact->uuid, $fieldsArray['body'] ?? '');
    
        // تحضير الرأس (header) للرسالة التفاعلية إن وجدت
        $header = $this->prepareHeader($fieldsArray);
        $buttonArray = [];
        $buttonType = null;
        $buttonLabel = null;
    
        if($type == 'text'){
            $buttonType = 'text';
        } elseif ($type === 'interactive buttons') {
            $buttonType = ($fieldsArray['buttonType'] ?? '') === 'buttons'
                ? 'interactive buttons'
                : 'interactive call to action url';
            $buttonArray = $this->prepareButtonArray($fieldsArray, $buttonType);
    
        } elseif ($type === 'interactive list') {
            $buttonType = 'interactive list';
            $buttonArray = $this->prepareButtonArray($fieldsArray, $buttonType);
            $buttonLabel = $fieldsArray['buttonLabel'] ?? null;
        }
    
        $response = null;
    
        switch ($type) {
            case 'text':
                $response = $this->whatsappService->sendMessage($contact->uuid, $message, 0, $buttonType);
                break;
    
            case 'media':
                $mediaInfo = json_decode($fieldsArray['media']['metadata'] ?? '{}', true);
                $mediaLocation = $fieldsArray['media']['location'] ?? '';
                $mediaLocation = ($mediaLocation === 'aws') ? 'amazon' : $mediaLocation;
    
                $response = $this->whatsappService->sendMedia(
                    $contact->uuid,
                    $fieldsArray['mediaType'] ?? '',
                    $mediaInfo['name'] ?? '',
                    $fieldsArray['media']['path'] ?? '',
                    $fieldsArray['media']['path'] ?? '',
                    $mediaLocation,
                    $fieldsArray['caption'] ?? ''
                );
                break;
    
            case 'interactive buttons':
            case 'interactive list':
                $response = $this->whatsappService->sendMessage(
                    $contact->uuid,
                    $message,
                    0,
                    $buttonType,
                    $buttonArray,
                    $header,
                    $fieldsArray['footer'] ?? '',
                    $buttonLabel
                );
                break;
        }
    
        if($response){
            FlowUserData::where('contact_id', $contactId)->update(['current_step' => $metadataArray['id']]);
            if(isset($response->data->chat->id)){
                FlowLog::create([
                    'flow_id' => $flowData->flow_id,
                    'chat_id' => $response->data->chat->id
                ]);
                return true;
            }
        }
    
        return false;
    }


    private function prepareHeader(array $fieldsArray): array
    {
        $header = [];

        if (($fieldsArray['headerType'] ?? '') === 'text') {
            $header = [
                'type' => 'text',
                'text' => clean($fieldsArray['headerText'] ?? ''),
            ];
        } elseif (($fieldsArray['headerType'] ?? '') !== 'none') {
            $header['type'] = $fieldsArray['headerType'] ?? '';
            $header[$fieldsArray['headerType'] ?? ''] = [
                'link' => $fieldsArray['headerMedia']['path'] ?? '',
            ];
        }

        return $header;
    }

    private function prepareButtonArray(array $fieldsArray, string $type): array
    {
        $buttonArray = [];

        if ($type === 'interactive buttons') {
            foreach ($fieldsArray['buttons'] ?? [] as $id => $title) {
                if (!empty($title)) {
                    $buttonArray[] = [
                        'id' => $id,
                        'title' => $title,
                    ];
                }
            }
        } elseif ($type === 'interactive call to action url') {
            $buttonArray = [
                'display_text' => $fieldsArray['ctaUrlButton']['displayText'] ?? '',
                'url' => $fieldsArray['ctaUrlButton']['url'] ?? '',
            ];
        } elseif ($type === 'interactive list') {
            $buttonArray = collect($fieldsArray['sections'] ?? [])->map(function ($section) {
                return [
                    'title' => $section['title'] ?? '',
                    'rows' => collect($section['rows'] ?? [])->map(function ($row) {
                        return [
                            'id' => $row['id'] ?? '',
                            'title' => $row['title'] ?? '',
                            'description' => $row['description'] ?? '',
                        ];
                    })->all()
                ];
            })->all();
        }

        return $buttonArray;
    }

    private function findEdgesBySource($edges, $sourceId, $message)
    {
        // Convert $sourceId to a string to handle loose type matching
        $sourceId = (string) $sourceId;
        
        // Initialize an empty array to store matching edges
        $matchingEdges = [];

        // Iterate over each edge to find matches with sourceId
        foreach ($edges as $index => $edge) {
            // Check if 'source' key exists and matches the source ID
            if (isset($edge['source']) && (string) $edge['source'] === $sourceId) {
                // If there's a match, add this edge to the matching array
                $matchingEdges[] = $edge;
            }
        }

        if (count($matchingEdges) === 1) {
            return $matchingEdges[0]['targetNode'] ?? [];
        } else if (count($matchingEdges) > 1) {
            $firstEdge = $matchingEdges[0];
            $type = $firstEdge['sourceNode']['data']['metadata']['fields']['type'] ?? null;

            // Perform logic based on the 'type'
            if ($type == 'interactive buttons') {
                $buttons = $firstEdge['sourceNode']['data']['metadata']['fields']['buttons'] ?? [];

                // Define button mapping
                $buttonMapping = ['button1' => 'a', 'button2' => 'b', 'button3' => 'c'];
                $handle = null;

                // Check for a match with the message in the buttons array
                foreach ($buttons as $buttonKey => $buttonValue) {
                    if (strtolower(trim($buttonValue)) === $message) {
                        $handle = $buttonMapping[$buttonKey] ?? null;
                    }
                }

                
                if($handle != null){
                    // Search for the edge with this handle and return its targetNode
                    foreach ($matchingEdges as $edge) {
                        if (isset($edge['sourceHandle']) && $edge['sourceHandle'] === $handle) {
                            return $edge['targetNode'] ?? [];
                        }
                    }
                }
            } elseif ($type == 'interactive list') {
                $sections = $firstEdge['sourceNode']['data']['metadata']['fields']['sections'] ?? [];
                $handle = null;

                // Search for the matching title and generate the handle dynamically
                foreach ($sections as $sectionIndex => $section) {
                    if (isset($section['rows']) && is_array($section['rows'])) {
                        foreach ($section['rows'] as $rowIndex => $row) {
                            if (isset($row['title']) && strtolower(trim($row['title'])) === strtolower(trim($message))) {
                                // Construct the handle using section and row positions
                                $handle = 'a' . $sectionIndex . $rowIndex;
                            }
                        }
                    }
                }

                if($handle != null){
                    foreach ($matchingEdges as $edge) {
                        if (isset($edge['sourceHandle']) && $edge['sourceHandle'] === $handle) {
                            return $edge['targetNode'] ?? [];
                        }
                    }
                }
            }

            return [];
        }

        return [];
    }

     private function findStepByButtonOrListTitle ($edges, $message)
    {
        foreach ($edges as $edge) {
            $type = $edge['sourceNode']['data']['metadata']['fields']['type'] ?? null;

            if ($type == 'interactive buttons') {
                $buttons = $edge['sourceNode']['data']['metadata']['fields']['buttons'] ?? [];

                foreach ($buttons as $buttonValue) {
                    if (strtolower(trim($buttonValue)) === strtolower(trim($message))) {
                        return $edge['source']; // ID of the matching node
                    }
                }
            } elseif ($type == 'interactive list') {
                $sections = $edge['sourceNode']['data']['metadata']['fields']['sections'] ?? [];

                foreach ($sections as $sectionIndex => $section) {
                    foreach ($section['rows'] ?? [] as $rowIndex => $row) {
                        if (strtolower(trim($row['title'] ?? '')) === strtolower(trim($message))) {
                            return $edge['source'];
                        }
                    }
                }
            }
        }

        return null;
    }

    private function replacePlaceholders($contactUuid, $message){
        $organization = Organization::where('id', $this->organizationId)->first();
        $contact = Contact::with('contactGroups')->where('uuid', $contactUuid)->first();
        $address = $contact->address ? json_decode($contact->address, true) : [];
        $metadata = $contact->metadata ? json_decode($contact->metadata, true) : [];
        $full_address = ($address['street'] ?? Null) . ', ' .
                        ($address['city'] ?? Null) . ', ' .
                        ($address['state'] ?? Null) . ', ' .
                        ($address['zip'] ?? Null) . ', ' .
                        ($address['country'] ?? Null);

        $data = [
            'first_name' => $contact->first_name ?? Null,
            'last_name' => $contact->last_name ?? Null,
            'full_name' => $contact->full_name ?? Null,
            'email' => $contact->email ?? Null,
            'phone' => $contact->phone ?? Null,
            'organization_name' => $organization->name,
            'full_address' => $full_address,
            'street' => $address['street'] ?? Null,
            'city' => $address['city'] ?? Null,
            'state' => $address['state'] ?? Null,
            'zip_code' => $address['zip'] ?? Null,
            'country' => $address['country'] ?? Null,
        ];

        $transformedMetadata = [];
        if($metadata){
            foreach ($metadata as $key => $value) {
                $transformedKey = strtolower(str_replace(' ', '_', $key));
                $transformedMetadata[$transformedKey] = $value;
            }
        }

        $mergedData = array_merge($data, $transformedMetadata);

        //Log::info($mergedData);

        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($mergedData) {
            $key = $matches[1];
            return isset($mergedData[$key]) ? $mergedData[$key] : $matches[0];
        }, $message);
    }
}