<script setup>
import { Head } from '@inertiajs/vue3';
import { router, useForm } from '@inertiajs/vue3';
import Layout from '@/Pages/User/Layout/App.vue';

const props = defineProps({
    title: String,
    accounts: Array,
    isPopup: Boolean,
});

const form = useForm({
    account_id: null,
});

const selectAccount = (accountId) => {
    form.account_id = accountId;
    form.post('/auth/instagram/save-account', {
        onSuccess: () => {
            if (props.isPopup && window.opener) {
                window.opener.postMessage({
                    type: 'oauth_success',
                    message: 'Instagram account connected successfully!'
                }, window.location.origin);
                window.close();
            }
        }
    });
};
</script>

<template>
    <Layout>
        <Head :title="title" />
        
        <div class="py-6 px-4 md:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $t('Select Instagram Account') }}</h1>
                    <p class="text-gray-600 mb-6">{{ $t('Choose which Instagram Business account you want to connect for posting') }}</p>

                    <div class="space-y-4">
                        <div v-for="account in accounts" :key="account.id" class="border-2 border-gray-200 rounded-lg p-4 hover:border-pink-500 transition cursor-pointer" @click="selectAccount(account.id)">
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="text-gray-400">
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
</template>

