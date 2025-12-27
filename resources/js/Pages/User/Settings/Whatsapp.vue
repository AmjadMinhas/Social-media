<template>
    <SettingLayout :modules="props.modules">
        <div class="md:h-[90vh]">
            <div class="flex justify-center items-center mb-8">
                <div class="md:w-[60em]">
                    <div v-if="!settings?.whatsapp" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="px-4 pt-2 pb-4">
                            <h2 class="text-[17px] mb-2">{{ $t('Setup Whatsapp Account') }}</h2>
                            <p class="text-sm text-gray-600 mb-4">
                                {{ $t('Setup your integration to be able to receive and send messages via Whatsapp.') }}
                            </p>
                            
                            <!-- Facebook Login (Embedded Signup) - Only if credentials are configured -->
                            <div v-if="embeddedSignupActive == 1 && props.appId && props.configId" class="mb-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-sm mb-1">{{ $t('Connect with Facebook (Recommended)') }}</h3>
                                            <p class="text-xs text-gray-600 mb-3">
                                                {{ $t('Use Facebook Login to quickly connect your WhatsApp Business Account. You will be guided through selecting your business portfolio, WhatsApp Business Account, and phone number.') }}
                                            </p>
                                            <EmbeddedSignupBtn :appId="props.appId" :configId="props.configId" :graphAPIVersion="props.graphAPIVersion"/>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Manual Setup Alternative -->
                                <div class="mt-3 text-center">
                                    <button 
                                        @click="openModal" 
                                        type="button" 
                                        class="text-sm text-gray-600 hover:text-primary underline"
                                    >
                                        {{ $t('Or setup manually with API credentials') }}
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Manual Setup Only (When Embedded Signup credentials not configured) -->
                            <div v-else class="mb-4">
                                <div class="bg-white border border-slate-200 rounded-lg p-4">
                                    <h3 class="font-semibold text-sm mb-2">{{ $t('Setup WhatsApp with API Credentials') }}</h3>
                                    <p class="text-xs text-gray-600 mb-4">
                                        {{ $t('Enter your WhatsApp Business API credentials to connect your account.') }}
                                    </p>
                                    <button @click="openModal" type="button" class="bg-primary text-white p-2 rounded-lg text-sm flex px-4 w-fit hover:shadow-md transition-shadow cursor-pointer">
                                        {{ $t('Setup WhatsApp Manually') }}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" class="ml-2"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><g opacity=".2"><path d="M12.206 5.848a1.5 1.5 0 0 1 2.113.192l3.333 4a1.5 1.5 0 1 1-2.304 1.92l-3.334-4a1.5 1.5 0 0 1 .192-2.112Z"/><path d="M12.206 16.152a1.5 1.5 0 0 1-.192-2.112l3.334-4a1.5 1.5 0 0 1 2.304 1.92l-3.333 4a1.5 1.5 0 0 1-2.113.192Z"/><path d="M16 11a1.5 1.5 0 0 1-1.5 1.5h-8a1.5 1.5 0 0 1 0-3h8A1.5 1.5 0 0 1 16 11Z"/></g><path d="M11.347 5.616a.5.5 0 0 1 .704.064l3.333 4a.5.5 0 0 1-.768.64l-3.333-4a.5.5 0 0 1 .064-.704Z"/><path d="M11.347 14.384a.5.5 0 0 1-.064-.704l3.333-4a.5.5 0 0 1 .768.64l-3.333 4a.5.5 0 0 1-.704.064Z"/><path d="M15.5 10a.5.5 0 0 1-.5.5H5a.5.5 0 0 1 0-1h20a.5.5 0 0 1 .5.5Z"/></g></svg>
                                    </button>
                                    
                                    <div v-if="embeddedSignupActive == 1" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p class="text-xs text-blue-800 mb-1">
                                            <strong>{{ $t('Note:') }}</strong> {{ $t('To enable Facebook Login flow, you need to configure WhatsApp Client ID and Config ID in admin settings.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="settings?.whatsapp" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="grid grid-cols-4 items-center px-4 gap-x-4 py-2 border-b relative">
                            <div class="border-r">
                                <div>{{ $t('Display name') }}</div>
                                <div>{{ settings.whatsapp?.verified_name }}</div>
                            </div>
                            <div class="border-r">
                                <div>{{ $t('Connected number') }}</div>
                                <div>{{ settings.whatsapp?.display_phone_number }}</div>
                            </div>
                            <div class="border-r">
                                <div>{{ $t('Message limits') }}</div>
                                <div>{{ settings.whatsapp?.messaging_limit_tier ? settings.whatsapp?.messaging_limit_tier : 'N/A' }}</div>
                            </div>
                            <div>
                                <div>{{ $t('Number status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ settings.whatsapp?.number_status }}</div>
                            </div>
                            <button v-if="refreshLoading === false" @click="refreshData()" class="flex items-center absolute right-0 top-0 text-xs mr-1 space-x-2 p-1 px-2 bg-slate-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.658 7.658 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.158 9.158 0 0 0 12.08 2.25m8.761 8.217a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.382 3.83-3.651 6.833-7.644 6.833a7.697 7.697 0 0 1-6.565-3.644a.75.75 0 1 0-1.277.788a9.197 9.197 0 0 0 7.842 4.356c4.808 0 8.765-3.66 9.15-8.333H22a.75.75 0 0 0 .527-1.284z"/></svg>
                                <span>{{ $t('Refresh') }}</span>
                            </button>
                            <button v-else class="flex items-center absolute right-0 top-0 text-xs mr-1 space-x-2 p-1 px-2 bg-slate-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-4 items-center px-4 gap-x-4 py-2">
                            <div class="border-r">
                                <div>{{ $t('Whatsapp business ac ID') }}</div>
                                <div>{{ settings.whatsapp?.waba_id }}</div>
                            </div>
                            <div v-if="settings.whatsapp?.is_embedded_signup == 1" class="border-r">
                                <div>{{ $t('Phone verification status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ settings.whatsapp?.code_verification_status }}</div>
                            </div>
                            <div class="border-r">
                                <div>{{ $t('Quality rating') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ settings.whatsapp?.quality_rating }}</div>
                            </div>
                            <div>
                                <div>{{ $t('Account status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ settings.whatsapp?.account_review_status }}</div>
                            </div>
                        </div>
                        
                        <!-- Concurrent Mode & Multi-Device Status -->
                        <div v-if="settings?.whatsapp" class="grid grid-cols-2 items-center px-4 gap-x-4 py-3 border-t">
                            <div>
                                <div class="text-xs text-slate-600 mb-1">{{ $t('Concurrent Mode') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">
                                    <span v-if="settings.whatsapp?.concurrent_mode_enabled" class="text-green-600">● {{ $t('Enabled') }}</span>
                                    <span v-else class="text-gray-500">○ {{ $t('Disabled') }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ $t('Business App + API simultaneously') }}</p>
                            </div>
                            <div>
                                <div class="text-xs text-slate-600 mb-1">{{ $t('Multi-Device Support') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">
                                    <span v-if="settings.whatsapp?.multi_device_enabled !== false" class="text-green-600">● {{ $t('Enabled') }}</span>
                                    <span v-else class="text-gray-500">○ {{ $t('Disabled') }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ $t('Multiple devices logged in') }}</p>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="submitForm2()" v-if="settings?.whatsapp" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-4">
                        <div class="flex items-center justify-between px-4 pt-2 pb-4">
                            <div>
                                <h2 class="text-[17px]">{{ $t('Business profile settings') }}</h2>
                                <span class="flex items-center mt-1">
                                    <svg class="mr-2" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    {{ $t('Setup the Whatsapp business profile for your number') }}
                                </span>
                            </div>
                            <div>
                                <button v-if="settings?.whatsapp && settings?.whatsapp?.is_embedded_signup === 0" type="button" @click="openModal2()" class="bg-primary text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit">
                                    {{ $t('Update token') }}
                                </button>
                            </div>
                        </div>
                        <div class="flex space-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Whatsapp profile picture') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Add/update your profile picture') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormImageLogo v-model="form2.profile_picture_url" :name="''" :error="form2.errors.profile_picture_url" :label="$t('Upload logo')" :imageUrl="form2.profile_picture_url" :class="'col-span-4 ml-6'"/>
                                <div class="ml-6">{{ $t('Accepted formats: JPG/PNG') }}</div>
                                <div class="ml-6">{{ $t('Minimum dimensions: 192x192 pixels.') }}</div>
                            </div>
                        </div>
                        <div class="flex space-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business address') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Specify your physical business address') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormInput v-model="form2.address" :error="form2.errors.address" :name="''" :type="'text'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex space-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business email') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Add your business email address') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormInput v-model="form2.email" :error="form2.errors.email" :name="''" :type="'email'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex space-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business description') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Edit your whatsapp business account description') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormTextArea v-model="form2.description" :error="form2.errors.description" :name="''" :type="'text'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex space-x-10 w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business industry') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Specify your business vertical') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormSelect v-model="form2.industry" :name="''" :type="'text'"  :options="options" :error="form2.errors.industry" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex px-4 pt-1 pb-2">
                            <div class="ml-auto">
                                <button type="submit" class="float-right rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm hover:shadow-md hover:bg-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" :disabled="form2.processing">
                                    <svg v-if="form2.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                                    <span v-else>{{ $t('Save') }}</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <div v-if="settings?.whatsapp && settings?.whatsapp?.is_embedded_signup === 0" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="px-4 pt-2 pb-4">
                            <h2 class="text-[17px]">{{ $t('Meta webhook settings') }}</h2>
                            <span class="flex items-center mt-1">
                                <svg class="mr-2" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                {{ $t('Add these webhook settings to your facebook developer account') }}
                            </span>
                        </div>
                        <div class="flex space-x-10 border-b w-full px-4 py-4">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Webhook url') }}</span>
                            </div>
                            <div class="text-left w-[60%]">
                                <span class="w-48 break-all">{{ currentURL + '/webhook/whatsapp/' +props.settings.identifier }}</span>
                            </div>
                        </div>
                        <div class="flex space-x-10 w-full px-4 py-4">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Verify token') }}</span>
                            </div>
                            <div class="text-left w-[60%]">
                                <div class="text-left">{{ props.settings.identifier }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp Coexistence Setup Guide -->
                    <div v-if="settings?.whatsapp" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="px-4 pt-2 pb-4 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-[17px]">{{ $t('WhatsApp Coexistence Setup') }}</h2>
                                    <span class="flex items-center mt-1 text-xs text-gray-600">
                                        {{ $t('Use the same number for both WhatsApp Business App and Cloud API simultaneously') }}
                                    </span>
                                </div>
                                <button 
                                    v-if="coexistenceStatus?.support_check?.supported && !coexistenceStatus?.enabled"
                                    @click="enableCoexistence()" 
                                    class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors"
                                >
                                    {{ $t('Enable Coexistence') }}
                                </button>
                                <span 
                                    v-else-if="coexistenceStatus?.enabled"
                                    class="bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm"
                                >
                                    {{ $t('Coexistence Enabled') }}
                                </span>
                            </div>
                        </div>

                        <!-- Support Status -->
                        <div v-if="coexistenceStatus?.support_check" class="px-4 py-3 border-b">
                            <div class="flex items-start space-x-3">
                                <div v-if="coexistenceStatus.support_check.supported" class="flex-shrink-0 mt-1">
                                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div v-else class="flex-shrink-0 mt-1">
                                    <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium" :class="coexistenceStatus.support_check.supported ? 'text-green-800' : 'text-red-800'">
                                        {{ coexistenceStatus.support_check.message }}
                                    </p>
                                    <p v-if="coexistenceStatus.support_check.country_code" class="text-xs text-gray-600 mt-1">
                                        {{ $t('Country Code') }}: {{ coexistenceStatus.support_check.country_code }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div v-if="coexistenceStatus?.requirements" class="px-4 py-3 border-b">
                            <h3 class="text-sm font-semibold mb-2">{{ $t('Requirements') }}</h3>
                            <ul class="space-y-2 text-xs">
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>{{ $t('WhatsApp Business App version') }} {{ coexistenceStatus.requirements.business_app_version?.required }} {{ $t('or higher') }}</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>{{ coexistenceStatus.requirements.country_support?.description }}</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>{{ coexistenceStatus.requirements.waiting_period?.description }}</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>{{ coexistenceStatus.requirements.facebook_page_link?.description }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Setup Instructions -->
                        <div v-if="coexistenceStatus?.setup_instructions" class="px-4 py-3">
                            <button 
                                @click="showCoexistenceGuide = !showCoexistenceGuide"
                                class="flex items-center justify-between w-full text-left text-sm font-semibold hover:text-primary transition-colors"
                            >
                                <span>{{ $t('View Setup Guide') }}</span>
                                <svg 
                                    class="w-5 h-5 transition-transform" 
                                    :class="{ 'rotate-180': showCoexistenceGuide }"
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <div v-if="showCoexistenceGuide" class="mt-4 space-y-4 text-xs">
                                <div v-for="(section, index) in coexistenceStatus.setup_instructions.sections" :key="index" class="border-l-2 border-primary pl-4">
                                    <h4 class="font-semibold mb-2">{{ section.title }}</h4>
                                    <ul class="space-y-1 text-gray-700">
                                        <li v-for="(item, itemIndex) in section.items" :key="itemIndex" class="flex items-start">
                                            <span class="mr-2">•</span>
                                            <span>{{ item }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Facebook Page Link Steps -->
                        <div v-if="coexistenceStatus?.requirements?.facebook_page_link?.steps" class="px-4 py-3 border-t">
                            <h3 class="text-sm font-semibold mb-3">{{ $t('How to Link Facebook Page') }}</h3>
                            <ol class="space-y-3 text-xs">
                                <li v-for="step in coexistenceStatus.requirements.facebook_page_link.steps" :key="step.step" class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs font-semibold mr-3">
                                        {{ step.step }}
                                    </span>
                                    <div class="flex-1">
                                        <p class="font-medium">{{ step.action }}</p>
                                        <p class="text-gray-600 mt-1">{{ step.description }}</p>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </div>

                    <div v-if="settings?.whatsapp" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-20">
                        <div class="flex items-center px-4 pt-2 pb-4">
                            <div class="w-[60%]">
                                <h2 class="text-[17px]">{{ $t('Remove Whatsapp account') }}</h2>
                                <span class="flex items-center mt-1">
                                    {{ $t('This will completely delete your whatsapp integration. Your contacts & messages will be unaffected.') }}
                                </span>
                            </div>
                            <div class="w-[40%] ml-auto">
                                <button @click="deleteIntegration()" class="float-right rounded-md bg-red-700 px-3 py-2 text-sm text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Delete integration')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :label="$t('Whatsapp API config')" :isOpen="isOpenFormModal" :closeBtn="true" @close="isOpenFormModal = false">
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm()" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">

                    <FormInput v-model="form.app_id" :error="form.errors.app_id" :name="$t('App ID')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.access_token" :error="form.errors.access_token" :name="$t('Access token')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.phone_number_id" :error="form.errors.phone_number_id" :name="$t('Phone number ID')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.waba_id" :error="form.errors.waba_id" :name="$t('Whatsapp business account ID')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.device_name" :error="form.errors.device_name" :name="$t('Device Name')" :type="'text'" :class="'sm:col-span-6'"/>
                    
                    <div class="sm:col-span-6">
                        <div class="flex items-center mb-4">
                            <input 
                                id="enable_concurrent_mode" 
                                type="checkbox" 
                                v-model="form.enable_concurrent_mode"
                                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                            />
                            <label for="enable_concurrent_mode" class="ml-2 block text-sm text-gray-900">
                                {{ $t('Enable Concurrent Mode (Business App + API)') }}
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 ml-6 mb-4">
                            {{ $t('Allow using WhatsApp Business App and API simultaneously on the same number') }}
                        </p>
                        
                        <div class="flex items-center">
                            <input 
                                id="enable_multi_device" 
                                type="checkbox" 
                                v-model="form.enable_multi_device"
                                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                            />
                            <label for="enable_multi_device" class="ml-2 block text-sm text-gray-900">
                                {{ $t('Enable Multi-Device Support') }}
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 ml-6">
                            {{ $t('Allow accounts to be logged into multiple devices') }}
                        </p>
                    </div>

                    <div class="mt-4 flex">
                        <button type="button" @click.self="isOpenFormModal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 mr-4">{{ $t('Cancel') }}</button>
                        <button 
                            :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal v-if="settings?.whatsapp && settings?.whatsapp?.is_embedded_signup === 0" :label="$t('Whatsapp API config')" :isOpen=isOpenForm2Modal>
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm3()" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                    <FormInput v-model="form3.access_token" :error="form3.errors.access_token" :name="$t('Access token')" :type="'text'" :class="'sm:col-span-6'"/>

                    <div class="mt-4 flex">
                        <button type="button" @click.self="isOpenForm2Modal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 mr-4">{{ $t('Cancel') }}</button>
                        <button 
                            :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
                            :disabled="form3.processing"
                        >
                            <svg v-if="form3.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./Layout.vue";
    import { ref, nextTick } from 'vue';
    import EmbeddedSignupBtn from '@/Components/EmbeddedSignupBtn.vue';
    import FormModal from '@/Components/FormModal.vue';
    import FormImageLogo from '@/Components/FormImageLogo.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import Modal from '@/Components/Modal.vue';
    import { trans } from 'laravel-vue-i18n';
    import { router, useForm } from "@inertiajs/vue3";

    const props = defineProps(['settings', 'embeddedSignupActive', 'appId', 'configId', 'graphAPIVersion', 'modules', 'coexistenceStatus']);
    const statusView = ref(false);
    const config = ref(props.settings.metadata);
    const currentURL = ref(window.location.origin);
    const isOpenFormModal = ref(false);
    const isOpenForm2Modal = ref(false);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const refreshLoading = ref(false);
    const showCoexistenceGuide = ref(false);
    const coexistenceStatus = ref(props.coexistenceStatus || null);
    const form = useForm({
        app_id: settings.value && settings.value.whatsapp ? settings.value.whatsapp.app_id : null,
        access_token: settings.value && settings.value.whatsapp ? settings.value.whatsapp.access_token : null,
        phone_number_id: settings.value && settings.value.whatsapp ? settings.value.whatsapp.phone_number_id : null,
        waba_id: settings.value && settings.value.whatsapp ? settings.value.whatsapp.waba_id : null,
        device_name: 'Primary Device',
        enable_concurrent_mode: settings.value && settings.value.whatsapp ? (settings.value.whatsapp.concurrent_mode_enabled ?? false) : false,
        enable_multi_device: settings.value && settings.value.whatsapp ? (settings.value.whatsapp.multi_device_enabled ?? true) : true,
    });

    const form2 = useForm({
        profile_picture_url: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.profile_picture_url : null,
        description: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.description : null,
        address: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.address : null,
        email: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.email : null,
        industry: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.industry : null,
        websites: []
    });

    const form3 = useForm({
        access_token: settings.value && settings.value.whatsapp ? settings.value.whatsapp.access_token : null,
    });

    const options = [
        { label: 'Automotive', value: 'AUTO' },
        { label: 'Beauty, spa and salon', value: 'BEAUTY' },
        { label: 'Clothing', value: 'APPAREL' },
        { label: 'Education', value: 'EDU' },
        { label: 'Entertainment', value: 'ENTERTAIN' },
        { label: 'Event planning and service', value: 'EVENT_PLAN' },
        { label: 'Finance and banking', value: 'FINANCE' },
        { label: 'Food and groceries', value: 'GROCERY' },
        { label: 'Public service', value: 'GOVT' },
        { label: 'Hotel and lodging', value: 'HOTEL' },
        { label: 'Medical and health', value: 'HEALTH' },
        { label: 'Charity', value: 'NONPROFIT' },
        { label: 'Professional services', value: 'PROF_SERVICES' },
        { label: 'Shopping and retail', value: 'RETAIL' },
        { label: 'Travel and transportation', value: 'TRAVEL' },
        { label: 'Restaurant', value: 'RESTAURANT' },
        { label: 'Not a business', value: 'NOT_A_BIZ' },
        { label: 'Undefined', value: 'UNDEFINED' },
        { label: 'Other', value: 'OTHER' },
    ]

    function openModal() {
        isOpenFormModal.value = true;
    }

    function openModal2() {
        isOpenForm2Modal.value = true;
    }

    const capitalizeString = (string) => {
        return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
    };

    const toggleStatusView = () => {
        statusView.value = !statusView.value;
    }

    const submitForm = () => {
        form.post('/settings/whatsapp', {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                isOpenFormModal.value = false
            }
        })
    }

    const submitForm2 = () => {
        form2.post('/settings/whatsapp/business-profile', {
            preserveScroll: true,
        })
    }

    const submitForm3 = () => {
        form3.post('/settings/whatsapp/token', {
            preserveScroll: true,
            onSuccess: () => {
                isOpenForm2Modal.value = false
            }
        })
    }

    const refreshData = () => {
        refreshLoading.value = true;

        router.visit(`/settings/whatsapp/refresh`, {
            method: 'get',
            preserveState: true,
            onFinish: () => {
                refreshLoading.value = false;
            },
            onSuccess: () => {
                router.visit('/settings/whatsapp', {
                    preserveState: false,
                });
            },
        })
    }

    const deleteIntegration = () => {
        router.delete(`/settings/whatsapp/business-profile`, {
            onBefore: () => confirm('Are you sure you want to delete your integration?'),
            preserveState: true,
            onSuccess: () => {
                router.visit('/settings/whatsapp', {
                    preserveState: false,
                });
            },
        })
    }

    const enableCoexistence = () => {
        router.post('/settings/whatsapp/coexistence/enable', {}, {
            preserveState: true,
            onSuccess: () => {
                router.visit('/settings/whatsapp', {
                    preserveState: false,
                });
            },
        })
    }
</script>