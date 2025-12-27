<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { router, useForm } from '@inertiajs/vue3';
import Layout from '@/Pages/User/Layout/App.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    title: String,
    accounts: Array,
    isPopup: Boolean,
});

const form = useForm({
    account_id: null,
});

const isLoading = ref(false);
const page = usePage();
const csrfToken = computed(() => {
    // Try to get CSRF token from Inertia props first, then fallback to meta tag
    return page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
});

const selectAccount = async (accountId) => {
    if (isLoading.value) return;
    
    isLoading.value = true;
    form.account_id = accountId;
    
    // If in popup mode, use regular fetch instead of Inertia form.post
    // This ensures we can properly handle the response and close the popup
    if (props.isPopup && window.opener) {
        try {
            const response = await fetch('/auth/instagram/save-account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken.value,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ account_id: accountId })
            });

            // Check if response is JSON before parsing
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                // If not JSON, it's probably an HTML error page
                const text = await response.text();
                throw new Error('Server returned an error page instead of JSON. Please try again.');
            }
            
            if (response.ok && data.success !== false) {
                // Send success message to parent window
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'oauth_success',
                        message: data.message || 'Instagram account connected successfully!'
                    }, window.location.origin);
                }
                
                // Close popup after a short delay to ensure message is sent
                setTimeout(() => {
                    if (!window.closed) {
                        window.close();
                    }
                }, 100);
            } else {
                // Send error message to parent window
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'oauth_error',
                        message: data.message || 'Failed to connect Instagram account'
                    }, window.location.origin);
                }
                
                setTimeout(() => {
                    if (!window.closed) {
                        window.close();
                    }
                }, 100);
            }
        } catch (error) {
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_error',
                    message: 'Failed to connect Instagram account: ' + error.message
                }, window.location.origin);
                
                setTimeout(() => {
                    if (!window.closed) {
                        window.close();
                    }
                }, 100);
            }
        } finally {
            isLoading.value = false;
        }
    } else {
        // Normal mode - use Inertia form.post
        form.post('/auth/instagram/save-account', {
            onSuccess: () => {
                // Handle success in normal mode
            },
            onError: () => {
                isLoading.value = false;
            },
            onFinish: () => {
                isLoading.value = false;
            }
        });
    }
};
</script>

<template>
    <Layout v-if="!isPopup">
        <Head :title="title" />
        
        <div class="py-6 px-4 md:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $t('Select Instagram Account') }}</h1>
                    <p class="text-gray-600 mb-6">{{ $t('Choose which Instagram Business account you want to connect for posting') }}</p>

                    <div class="space-y-4">
                        <div v-for="account in accounts" :key="account.id" class="border-2 border-gray-200 rounded-lg p-4 hover:border-pink-500 transition cursor-pointer" :class="{ 'opacity-50 cursor-not-allowed': isLoading }" @click="!isLoading && selectAccount(account.id)">
                            <div class="flex items-center space-x-4">
                                <img v-if="account.profile_picture" :src="account.profile_picture" :alt="account.username" class="w-16 h-16 rounded-full">
                                <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-orange-400 flex items-center justify-center text-white text-2xl font-bold">
                                    {{ account.username.charAt(0).toUpperCase() }}
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold">@{{ account.username }}</h3>
                                    <p class="text-sm text-gray-500">Connected to: {{ account.page_name }}</p>
                                    <p class="text-xs text-gray-400">ID: {{ account.id }}</p>
                                </div>
                                <div v-if="isLoading && form.account_id === account.id" class="animate-spin rounded-full h-6 w-6 border-b-2 border-pink-600"></div>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="text-gray-400">
                                    <path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 text-center">
                        <a href="/social-accounts" class="text-blue-600 hover:text-blue-800">
                            {{ $t('Cancel') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
    
    <!-- Popup mode - no Layout wrapper -->
    <div v-else class="min-h-screen bg-gray-50 py-6 px-4">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $t('Select Instagram Account') }}</h1>
                <p class="text-gray-600 mb-6">{{ $t('Choose which Instagram Business account you want to connect for posting') }}</p>

                <div class="space-y-4">
                    <div v-for="account in accounts" :key="account.id" class="border-2 border-gray-200 rounded-lg p-4 hover:border-pink-500 transition cursor-pointer" :class="{ 'opacity-50 cursor-not-allowed': isLoading }" @click="!isLoading && selectAccount(account.id)">
                        <div class="flex items-center space-x-4">
                            <img v-if="account.profile_picture" :src="account.profile_picture" :alt="account.username" class="w-16 h-16 rounded-full">
                            <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-orange-400 flex items-center justify-center text-white text-2xl font-bold">
                                {{ account.username.charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold">@{{ account.username }}</h3>
                                <p class="text-sm text-gray-500">Connected to: {{ account.page_name }}</p>
                                <p class="text-xs text-gray-400">ID: {{ account.id }}</p>
                            </div>
                            <div v-if="isLoading && form.account_id === account.id" class="animate-spin rounded-full h-6 w-6 border-b-2 border-pink-600"></div>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="text-gray-400">
                                <path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <button @click="window.close()" class="text-blue-600 hover:text-blue-800">
                        {{ $t('Cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

