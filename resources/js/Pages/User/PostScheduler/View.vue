<template>
    <AppLayout>
        <div class="md:flex md:flex-col bg-white border-l py-4 text-[#000] overflow-y-scroll">
            <div class="flex justify-between px-8 border-b pb-2">
                <div>
                    <h2 class="text-xl mb-1">{{ post.title }}</h2>
                    <p class="flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ml-1 mt-1">{{ $t('Scheduled Post Details') }}</span>
                    </p>
                </div>
                <div class="space-x-2 flex items-center">
                    <Link href="/post-scheduler" class="rounded-md bg-black px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500">
                        {{ $t('Back') }}
                    </Link>
                </div>
            </div>
            
            <div class="px-8 py-6 space-y-6">
                <!-- Status Banner -->
                <div :class="getStatusBannerClass(post.status)" class="p-4 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">{{ getStatusIcon(post.status) }}</span>
                            <div>
                                <h3 class="font-medium">{{ post.status_label }}</h3>
                                <p class="text-sm">{{ getStatusMessage(post.status) }}</p>
                            </div>
                        </div>
                        <div class="text-sm">
                            <span v-if="post.status === 'scheduled'">
                                {{ $t('Scheduled for') }}: <strong>{{ formatDate(post.scheduled_at) }}</strong>
                            </span>
                            <span v-if="post.status === 'published'">
                                {{ $t('Published at') }}: <strong>{{ formatDate(post.published_at) }}</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Error Message (if failed) -->
                <div v-if="post.status === 'failed' && post.error_message" class="bg-red-50 border border-red-200 p-4 rounded-lg">
                    <h4 class="font-medium text-red-800 mb-2">{{ $t('Error Details') }}</h4>
                    <p class="text-sm text-red-700">{{ post.error_message }}</p>
                </div>

                <!-- Post Content -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-medium mb-4">{{ $t('Post Content') }}</h3>
                    <div class="bg-white p-4 rounded-lg border">
                        <p class="whitespace-pre-wrap text-gray-800">{{ post.content }}</p>
                    </div>
                </div>

                <!-- Platforms -->
                <div>
                    <h3 class="text-lg font-medium mb-4">{{ $t('Target Platforms') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div 
                            v-for="platform in post.platforms" 
                            :key="platform"
                            class="flex items-center p-4 border-2 border-primary bg-blue-50 rounded-lg"
                        >
                            <div class="mr-3">
                                <!-- Facebook -->
                                <svg v-if="platform === 'facebook'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#1877F2" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                                <!-- Instagram -->
                                <svg v-else-if="platform === 'instagram'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><defs><radialGradient id="instagramGradient3" cx="30%" cy="110%"><stop offset="0%" style="stop-color:#fdf497" /><stop offset="5%" style="stop-color:#fdf497" /><stop offset="45%" style="stop-color:#fd5949" /><stop offset="60%" style="stop-color:#d6249f" /><stop offset="90%" style="stop-color:#285AEB" /></radialGradient></defs><path fill="url(#instagramGradient3)" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8A1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5a5 5 0 0 1-5 5a5 5 0 0 1-5-5a5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3a3 3 0 0 0 3 3a3 3 0 0 0 3-3a3 3 0 0 0-3-3z"/></svg>
                                <!-- TikTok -->
                                <svg v-else-if="platform === 'tiktok'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6c0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64c0 3.33 2.76 5.7 5.69 5.7c3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>
                                <!-- Twitter/X -->
                                <svg v-else-if="platform === 'twitter'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M18.205 2.25h3.308l-7.227 8.26l8.502 11.24H16.13l-5.214-6.817L4.95 21.75H1.64l7.73-8.835L1.215 2.25H8.04l4.713 6.231l5.45-6.231Zm-1.161 17.52h1.833L7.045 4.126H5.078L17.044 19.77Z"/></svg>
                                <!-- LinkedIn -->
                                <svg v-else-if="platform === 'linkedin'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#0A66C2" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium">{{ getPlatformName(platform) }}</h4>
                                <p v-if="post.platform_post_ids && post.platform_post_ids[platform]" class="text-xs text-gray-500">
                                    {{ $t('Post ID') }}: {{ post.platform_post_ids[platform] }}
                                </p>
                                <p v-else class="text-xs text-gray-500">{{ $t('Not published yet') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Media (if any) -->
                <div v-if="post.media && post.media.length > 0">
                    <h3 class="text-lg font-medium mb-4">{{ $t('Media') }}</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div v-for="(media, index) in post.media" :key="index" class="border rounded-lg overflow-hidden">
                            <img :src="media" :alt="'Media ' + (index + 1)" class="w-full h-32 object-cover">
                        </div>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="border-t pt-6">
                    <h3 class="text-lg font-medium mb-4">{{ $t('Details') }}</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ $t('Created At') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(post.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ $t('Last Updated') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(post.updated_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ $t('Scheduled Time') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(post.scheduled_at) }}</dd>
                        </div>
                        <div v-if="post.published_at">
                            <dt class="text-sm font-medium text-gray-500">{{ $t('Published Time') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(post.published_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Actions -->
                <div v-if="post.status === 'scheduled'" class="border-t pt-6 flex justify-end space-x-3">
                    <button 
                        @click="cancelPost" 
                        class="px-4 py-2 border border-red-300 text-red-700 rounded-md text-sm font-medium hover:bg-red-50"
                    >
                        {{ $t('Cancel Post') }}
                    </button>
                    <Link :href="`/post-scheduler/${post.uuid}/edit`" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary-dark">
                        {{ $t('Edit Post') }}
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { trans } from 'laravel-vue-i18n';

    const props = defineProps(['post']);

    const form = useForm({});

    const platformNames = {
        facebook: 'Facebook',
        instagram: 'Instagram',
        tiktok: 'TikTok',
        twitter: 'X (Twitter)',
        linkedin: 'LinkedIn'
    };

    const getPlatformName = (platform) => {
        return platformNames[platform] || 'Facebook';
    };

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const getStatusBannerClass = (status) => {
        const classes = {
            'scheduled': 'bg-blue-50 border border-blue-200',
            'publishing': 'bg-yellow-50 border border-yellow-200',
            'published': 'bg-green-50 border border-green-200',
            'failed': 'bg-red-50 border border-red-200',
            'cancelled': 'bg-gray-50 border border-gray-200'
        };
        return classes[status] || 'bg-gray-50 border border-gray-200';
    };

    const getStatusIcon = (status) => {
        const icons = {
            'scheduled': '📅',
            'publishing': '⏳',
            'published': '✅',
            'failed': '❌',
            'cancelled': '🚫'
        };
        return icons[status] || '📄';
    };

    const getStatusMessage = (status) => {
        const messages = {
            'scheduled': trans('This post is scheduled and will be published automatically'),
            'publishing': trans('This post is currently being published'),
            'published': trans('This post has been successfully published'),
            'failed': trans('This post failed to publish'),
            'cancelled': trans('This post has been cancelled')
        };
        return messages[status] || '';
    };

    const cancelPost = () => {
        if (confirm(trans('Are you sure you want to cancel this scheduled post?'))) {
            form.post(`/post-scheduler/${props.post.uuid}`, {
                _method: 'post',
                status: 'cancelled'
            });
        }
    };
</script>



