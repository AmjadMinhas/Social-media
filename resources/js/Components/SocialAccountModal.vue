<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    isOpen: Boolean,
    platform: String,
    platformName: String,
});

const emit = defineEmits(['close']);

const isLoading = ref(false);
const error = ref(null);
const popupWindow = ref(null);

const connectAccount = () => {
    if (!props.platform) return;
    
    isLoading.value = true;
    error.value = null;

    // Open OAuth in popup window
    const width = 600;
    const height = 700;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;

    const authUrl = `/auth/${props.platform}`;
    
    popupWindow.value = window.open(
        authUrl,
        `${props.platformName} OAuth`,
        `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,scrollbars=yes,resizable=yes`
    );

    // Listen for OAuth completion
    const checkClosed = setInterval(() => {
        if (popupWindow.value?.closed) {
            clearInterval(checkClosed);
            isLoading.value = false;
            // Refresh the page to show new account
            router.reload({ only: ['accounts'] });
        }
    }, 500);

    // Listen for message from popup (if callback sends postMessage)
    window.addEventListener('message', handleMessage);
};

const handleMessage = (event) => {
    // Verify origin for security
    if (event.origin !== window.location.origin) return;

    if (event.data.type === 'oauth_success') {
        if (popupWindow.value) {
            popupWindow.value.close();
        }
        isLoading.value = false;
        router.reload({ only: ['accounts'] });
        emit('close');
        window.removeEventListener('message', handleMessage);
    } else if (event.data.type === 'oauth_error') {
        error.value = event.data.message || 'Connection failed';
        isLoading.value = false;
        if (popupWindow.value) {
            popupWindow.value.close();
        }
        window.removeEventListener('message', handleMessage);
    }
};

const closeModal = () => {
    if (popupWindow.value && !popupWindow.value.closed) {
        popupWindow.value.close();
    }
    window.removeEventListener('message', handleMessage);
    emit('close');
};

watch(() => props.isOpen, (newVal) => {
    if (!newVal) {
        closeModal();
    }
});

const getPlatformIcon = (platform) => {
    const icons = {
        facebook: `<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"><path fill="#1877F2" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>`,
        linkedin: `<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"><path fill="#0A66C2" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>`,
        instagram: `<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"><defs><radialGradient id="instagramGradient" cx="30%" cy="110%"><stop offset="0%" style="stop-color:#fdf497" /><stop offset="5%" style="stop-color:#fdf497" /><stop offset="45%" style="stop-color:#fd5949" /><stop offset="60%" style="stop-color:#d6249f" /><stop offset="90%" style="stop-color:#285AEB" /></radialGradient></defs><path fill="url(#instagramGradient)" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8A1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5a5 5 0 0 1-5 5a5 5 0 0 1-5-5a5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3a3 3 0 0 0 3 3a3 3 0 0 0 3-3a3 3 0 0 0-3-3z"/></svg>`,
        twitter: `<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"><path fill="currentColor" d="M18.205 2.25h3.308l-7.227 8.26l8.502 11.24H16.13l-5.214-6.817L4.95 21.75H1.64l7.73-8.835L1.215 2.25H8.04l4.713 6.231l5.45-6.231Zm-1.161 17.52h1.833L7.045 4.126H5.078L17.044 19.77Z"/></svg>`,
    };
    return icons[platform] || '';
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-6 pt-6 pb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-2xl font-semibold text-gray-900">
                            {{ $t('Connect') }} {{ platformName }}
                        </h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>

                    <div class="text-center py-6">
                        <div class="flex justify-center mb-4" v-html="getPlatformIcon(platform)"></div>
                        
                        <p class="text-gray-600 mb-6">
                            {{ $t('You will be redirected to') }} {{ platformName }} {{ $t('to authorize this application.') }}
                        </p>

                        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm text-red-800">{{ error }}</p>
                        </div>

                        <div v-if="isLoading" class="mb-4">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <p class="mt-2 text-sm text-gray-600">{{ $t('Waiting for authorization...') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $t('Please complete the authorization in the popup window') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                    <button @click="closeModal" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        {{ $t('Cancel') }}
                    </button>
                    <button @click="connectAccount" :disabled="isLoading" type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ isLoading ? $t('Connecting...') : $t('Connect') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>


