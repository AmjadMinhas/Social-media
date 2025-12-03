<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Layout from '@/Pages/User/Layout/App.vue';
import AlertModal from '@/Components/AlertModal.vue';
import SocialAccountModal from '@/Components/SocialAccountModal.vue';
import { useAlertModal } from '@/Composables/useAlertModal';

const props = defineProps({
    title: String,
    accounts: Array,
});

const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

const isModalOpen = ref(false);
const selectedPlatform = ref(null);
const selectedPlatformName = ref('');

const disconnectAccount = (uuid) => {
    router.delete(`/social-accounts/${uuid}`);
};

const verifyAccount = (uuid) => {
    router.post(`/social-accounts/${uuid}/verify`);
};

const openConnectModal = (platform, platformName) => {
    selectedPlatform.value = platform;
    selectedPlatformName.value = platformName;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    selectedPlatform.value = null;
    selectedPlatformName.value = '';
};

const getPlatformIcon = (platform) => {
    const icons = {
        facebook: `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="#1877F2" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>`,
        linkedin: `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="#0A66C2" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>`,
        instagram: `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><defs><radialGradient id="instagramGradient" cx="30%" cy="110%"><stop offset="0%" style="stop-color:#fdf497" /><stop offset="5%" style="stop-color:#fdf497" /><stop offset="45%" style="stop-color:#fd5949" /><stop offset="60%" style="stop-color:#d6249f" /><stop offset="90%" style="stop-color:#285AEB" /></radialGradient></defs><path fill="url(#instagramGradient)" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8A1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5a5 5 0 0 1-5 5a5 5 0 0 1-5-5a5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3a3 3 0 0 0 3 3a3 3 0 0 0 3-3a3 3 0 0 0-3-3z"/></svg>`,
        twitter: `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="currentColor" d="M18.205 2.25h3.308l-7.227 8.26l8.502 11.24H16.13l-5.214-6.817L4.95 21.75H1.64l7.73-8.835L1.215 2.25H8.04l4.713 6.231l5.45-6.231Zm-1.161 17.52h1.833L7.045 4.126H5.078L17.044 19.77Z"/></svg>`,
        tiktok: `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="currentColor" d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6c0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64c0 3.33 2.76 5.7 5.69 5.7c3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>`,
    };
    return icons[platform] || '';
};

const getStatusBadge = (account) => {
    if (!account.is_active) {
        return { text: 'Inactive', color: 'bg-red-100 text-red-800' };
    }
    if (account.is_token_expired) {
        return { text: 'Expired', color: 'bg-orange-100 text-orange-800' };
    }
    return { text: 'Active', color: 'bg-green-100 text-green-800' };
};

const formatDate = (dateString) => {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Layout>
        <Head :title="title" />
        
        <div class="py-6 px-4 md:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $t('Social Media Accounts') }}</h1>
                    <p class="mt-2 text-gray-600">{{ $t('Connect your social media accounts to start scheduling posts') }}</p>
                </div>

                <!-- Connect New Account Section -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold mb-4">{{ $t('Connect New Account') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Facebook -->
                        <button @click="openConnectModal('facebook', 'Facebook')" class="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition">
                            <div class="flex items-center space-x-3">
                                <div v-html="getPlatformIcon('facebook')"></div>
                                <div class="text-left">
                                    <div class="font-semibold">Facebook</div>
                                    <div class="text-sm text-gray-500">{{ $t('Connect Page') }}</div>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>
                        </button>

                        <!-- LinkedIn -->
                        <button @click="openConnectModal('linkedin', 'LinkedIn')" class="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition">
                            <div class="flex items-center space-x-3">
                                <div v-html="getPlatformIcon('linkedin')"></div>
                                <div class="text-left">
                                    <div class="font-semibold">LinkedIn</div>
                                    <div class="text-sm text-gray-500">{{ $t('Connect Profile') }}</div>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>
                        </button>

                        <!-- Instagram -->
                        <button @click="openConnectModal('instagram', 'Instagram')" class="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg hover:border-pink-500 hover:bg-pink-50 transition">
                            <div class="flex items-center space-x-3">
                                <div v-html="getPlatformIcon('instagram')"></div>
                                <div class="text-left">
                                    <div class="font-semibold">Instagram</div>
                                    <div class="text-sm text-gray-500">{{ $t('Connect Business') }}</div>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>
                        </button>

                        <!-- Twitter/X -->
                        <button @click="openConnectModal('twitter', 'X (Twitter)')" class="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition">
                            <div class="flex items-center space-x-3">
                                <div v-html="getPlatformIcon('twitter')"></div>
                                <div class="text-left">
                                    <div class="font-semibold">X (Twitter)</div>
                                    <div class="text-sm text-gray-500">{{ $t('Connect Profile') }}</div>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>
                        </button>

                        <!-- TikTok -->
                        <button @click="openConnectModal('tiktok', 'TikTok')" class="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg hover:border-black hover:bg-gray-50 transition">
                            <div class="flex items-center space-x-3">
                                <div v-html="getPlatformIcon('tiktok')"></div>
                                <div class="text-left">
                                    <div class="font-semibold">TikTok</div>
                                    <div class="text-sm text-gray-500">{{ $t('Connect Profile') }}</div>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Connected Accounts -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold">{{ $t('Connected Accounts') }}</h2>
                    </div>
                    
                    <div v-if="accounts.length === 0" class="p-8 text-center text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" class="mx-auto mb-4 text-gray-300"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        <p class="text-lg">{{ $t('No accounts connected yet') }}</p>
                        <p class="text-sm mt-2">{{ $t('Connect your first social media account to get started') }}</p>
                    </div>

                    <div v-else class="divide-y divide-gray-200">
                        <div v-for="account in accounts" :key="account.uuid" class="p-6 hover:bg-gray-50 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4 flex-1">
                                    <div v-html="getPlatformIcon(account.platform)"></div>
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <h3 class="text-lg font-semibold">{{ account.platform_username }}</h3>
                                            <span :class="getStatusBadge(account).color" class="px-2 py-1 rounded-full text-xs">
                                                {{ getStatusBadge(account).text }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500">{{ account.platform_name }}</p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            {{ $t('Last used') }}: {{ formatDate(account.last_used_at) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <button v-if="!account.is_active || account.is_token_expired" @click="verifyAccount(account.uuid)" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition text-sm">
                                        {{ $t('Reconnect') }}
                                    </button>
                                    <button @click="openAlert($t('Are you sure you want to disconnect this account?'), disconnectAccount, [account.uuid])" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition text-sm">
                                        {{ $t('Disconnect') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-2">{{ $t('Need Help?') }}</h3>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="mr-2 mt-0.5 flex-shrink-0"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5l1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <span>{{ $t('For Facebook, make sure you have a Facebook Page (not just a personal profile)') }}</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="mr-2 mt-0.5 flex-shrink-0"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5l1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <span>{{ $t('LinkedIn tokens expire after 60 days. You\'ll need to reconnect periodically') }}</span>
                        </li>
                        <li class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="mr-2 mt-0.5 flex-shrink-0"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5l1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <span>{{ $t('Once connected, go to Post Scheduler to create and schedule your posts') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <AlertModal :isOpen="isOpenAlert" @confirm="confirmAlert" />
        <SocialAccountModal 
            :isOpen="isModalOpen" 
            :platform="selectedPlatform"
            :platformName="selectedPlatformName"
            @close="closeModal"
        />
    </Layout>
</template>

