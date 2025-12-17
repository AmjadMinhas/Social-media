<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { router, useForm } from '@inertiajs/vue3';
import Layout from '@/Pages/User/Layout/App.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    title: String,
    pages: Array,
    isPopup: Boolean,
});

const form = useForm({
    page_id: null,
});

const isLoading = ref(false);
const page = usePage();
const csrfToken = computed(() => {
    // Try to get CSRF token from Inertia props first, then fallback to meta tag
    return page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
});

const selectPage = async (pageId) => {
    if (isLoading.value) return;
    
    console.log('🔵 SelectFacebookPage: Starting page selection', {
        pageId,
        isPopup: props.isPopup,
        hasOpener: !!window.opener,
        pagesCount: props.pages?.length,
        csrfToken: csrfToken.value ? 'EXISTS' : 'MISSING'
    });
    
    isLoading.value = true;
    form.page_id = pageId;
    
    // If in popup mode, use regular fetch instead of Inertia form.post
    // This ensures we can properly handle the response and close the popup
    if (props.isPopup && window.opener) {
        try {
            console.log('🔵 SelectFacebookPage: Sending fetch request', {
                url: '/auth/facebook/save-page',
                pageId,
                hasCsrfToken: !!csrfToken.value
            });
            
            const response = await fetch('/auth/facebook/save-page', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken.value,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ page_id: pageId })
            });

            console.log('🔵 SelectFacebookPage: Response received', {
                status: response.status,
                statusText: response.statusText,
                contentType: response.headers.get('content-type'),
                ok: response.ok
            });

            // Check if response is JSON before parsing
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
                console.log('🔵 SelectFacebookPage: JSON data received', data);
            } else {
                // If not JSON, it's probably an HTML error page
                const text = await response.text();
                console.error('❌ SelectFacebookPage: Non-JSON response received', {
                    contentType,
                    status: response.status,
                    textPreview: text.substring(0, 500)
                });
                throw new Error('Server returned an error page instead of JSON. Please try again.');
            }
            
            if (response.ok && data.success !== false) {
                console.log('✅ SelectFacebookPage: Success! Sending message to parent', {
                    message: data.message,
                    hasOpener: !!window.opener
                });
                
                // Send success message to parent window
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'oauth_success',
                        message: data.message || 'Facebook page connected successfully!'
                    }, window.location.origin);
                    
                    console.log('✅ SelectFacebookPage: Message sent, closing popup');
                }
                
                // Close popup after a short delay to ensure message is sent
                setTimeout(() => {
                    if (!window.closed) {
                        window.close();
                    }
                }, 100);
            } else {
                console.error('❌ SelectFacebookPage: Error response', {
                    success: data.success,
                    message: data.message,
                    status: response.status
                });
                
                // Send error message to parent window
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'oauth_error',
                        message: data.message || 'Failed to connect Facebook page'
                    }, window.location.origin);
                }
                
                setTimeout(() => {
                    if (!window.closed) {
                        window.close();
                    }
                }, 100);
            }
        } catch (error) {
            console.error('❌ SelectFacebookPage: Exception caught', {
                error: error.message,
                stack: error.stack,
                hasOpener: !!window.opener
            });
            
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_error',
                    message: 'Failed to connect Facebook page: ' + error.message
                }, window.location.origin);
                
                setTimeout(() => {
                    if (!window.closed) {
                        window.close();
                    }
                }, 100);
            }
        } finally {
            isLoading.value = false;
            console.log('🔵 SelectFacebookPage: Process completed');
        }
    } else {
        // Normal mode - use Inertia form.post
        form.post('/auth/facebook/save-page', {
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
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $t('Select Facebook Page') }}</h1>
                    <p class="text-gray-600 mb-6">{{ $t('Choose which Facebook page you want to connect for posting') }}</p>

                    <div class="space-y-4">
                        <div v-for="page in pages" :key="page.id" class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-500 transition cursor-pointer" :class="{ 'opacity-50 cursor-not-allowed': isLoading }" @click="!isLoading && selectPage(page.id)">
                            <div class="flex items-center space-x-4">
                                <img v-if="page.picture && page.picture.data && page.picture.data.url" :src="page.picture.data.url" :alt="page.name" class="w-16 h-16 rounded-full">
                                <div v-else class="w-16 h-16 rounded-full bg-blue-500 flex items-center justify-center text-white text-2xl font-bold">
                                    {{ page.name.charAt(0) }}
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold">{{ page.name }}</h3>
                                    <p class="text-sm text-gray-500">ID: {{ page.id }}</p>
                                </div>
                                <div v-if="isLoading && form.page_id === page.id" class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
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
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $t('Select Facebook Page') }}</h1>
                <p class="text-gray-600 mb-6">{{ $t('Choose which Facebook page you want to connect for posting') }}</p>

                <div class="space-y-4">
                    <div v-for="page in pages" :key="page.id" class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-500 transition cursor-pointer" :class="{ 'opacity-50 cursor-not-allowed': isLoading }" @click="!isLoading && selectPage(page.id)">
                        <div class="flex items-center space-x-4">
                            <img v-if="page.picture && page.picture.data && page.picture.data.url" :src="page.picture.data.url" :alt="page.name" class="w-16 h-16 rounded-full">
                            <div v-else class="w-16 h-16 rounded-full bg-blue-500 flex items-center justify-center text-white text-2xl font-bold">
                                {{ page.name.charAt(0) }}
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold">{{ page.name }}</h3>
                                <p class="text-sm text-gray-500">ID: {{ page.id }}</p>
                            </div>
                            <div v-if="isLoading && form.page_id === page.id" class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
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
