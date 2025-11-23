<template>
    <AppLayout>
        <div class="md:flex md:flex-col bg-white border-l py-4 text-[#000] overflow-y-scroll">
            <div class="flex justify-between px-8 border-b pb-2">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Schedule New Post') }}</h2>
                    <p class="flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ml-1 mt-1">{{ $t('Create scheduled post for social media') }}</span>
                    </p>
                </div>
                <div class="space-x-2 flex items-center">
                    <Link href="/post-scheduler" class="rounded-md bg-black px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $t('Back') }}
                    </Link>
                </div>
            </div>
            
            <div class="px-8 py-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('Post Title') }} <span class="text-red-500">*</span>
                        </label>
                        <input 
                            v-model="form.title" 
                            type="text" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary"
                            :placeholder="$t('Enter post title')"
                            required
                        />
                        <div v-if="form.errors.title" class="text-red-500 text-sm mt-1">{{ form.errors.title }}</div>
                    </div>

                    <!-- Content -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('Post Content') }} <span class="text-red-500">*</span>
                        </label>
                        <textarea 
                            v-model="form.content" 
                            rows="6" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary"
                            :placeholder="$t('Write your post content here...')"
                            required
                        ></textarea>
                        <div class="text-sm text-gray-500 mt-1">{{ form.content.length }} characters</div>
                        <div v-if="form.errors.content" class="text-red-500 text-sm mt-1">{{ form.errors.content }}</div>
                    </div>

                    <!-- Platform Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            {{ $t('Select Platforms') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div 
                                v-for="platform in availablePlatforms" 
                                :key="platform.id"
                                @click="togglePlatform(platform.id)"
                                :class="form.platforms.includes(platform.id) ? 'border-primary bg-blue-50' : 'border-gray-300'"
                                class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-primary transition-colors"
                            >
                                <div class="mr-3">
                                    <!-- Facebook -->
                                    <svg v-if="platform.id === 'facebook'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#1877F2" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                                    <!-- Instagram -->
                                    <svg v-else-if="platform.id === 'instagram'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><defs><radialGradient id="instagramGradient2" cx="30%" cy="110%"><stop offset="0%" style="stop-color:#fdf497" /><stop offset="5%" style="stop-color:#fdf497" /><stop offset="45%" style="stop-color:#fd5949" /><stop offset="60%" style="stop-color:#d6249f" /><stop offset="90%" style="stop-color:#285AEB" /></radialGradient></defs><path fill="url(#instagramGradient2)" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8A1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5a5 5 0 0 1-5 5a5 5 0 0 1-5-5a5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3a3 3 0 0 0 3 3a3 3 0 0 0 3-3a3 3 0 0 0-3-3z"/></svg>
                                    <!-- TikTok -->
                                    <svg v-else-if="platform.id === 'tiktok'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6c0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64c0 3.33 2.76 5.7 5.69 5.7c3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>
                                    <!-- Twitter/X -->
                                    <svg v-else-if="platform.id === 'twitter'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M18.205 2.25h3.308l-7.227 8.26l8.502 11.24H16.13l-5.214-6.817L4.95 21.75H1.64l7.73-8.835L1.215 2.25H8.04l4.713 6.231l5.45-6.231Zm-1.161 17.52h1.833L7.045 4.126H5.078L17.044 19.77Z"/></svg>
                                    <!-- LinkedIn -->
                                    <svg v-else-if="platform.id === 'linkedin'" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#0A66C2" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium">{{ platform.name }}</h4>
                                    <p class="text-xs text-gray-500">{{ platform.description }}</p>
                                </div>
                                <div v-if="form.platforms.includes(platform.id)" class="text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19L21 7l-1.41-1.41L9 16.17z"/></svg>
                                </div>
                            </div>
                        </div>
                        <div v-if="form.errors.platforms" class="text-red-500 text-sm mt-1">{{ form.errors.platforms }}</div>
                    </div>

                    <!-- Scheduled Date & Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('Schedule Date & Time') }} <span class="text-red-500">*</span>
                        </label>
                        <input 
                            v-model="form.scheduled_at" 
                            type="datetime-local" 
                            :min="minDateTime"
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary"
                            required
                        />
                        <div class="text-sm text-gray-500 mt-1">{{ $t('Select when you want this post to be published') }}</div>
                        <div v-if="form.errors.scheduled_at" class="text-red-500 text-sm mt-1">{{ form.errors.scheduled_at }}</div>
                    </div>

                    <!-- Media Upload (placeholder for future) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('Media (Optional)') }}
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">{{ $t('Media upload will be available soon') }}</p>
                            <p class="text-xs text-gray-500">{{ $t('Images, Videos, GIFs') }}</p>
                        </div>
                    </div>

                    <!-- Preview Section -->
                    <div v-if="form.content" class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">{{ $t('Post Preview') }}</h3>
                        <div class="bg-white p-4 rounded-lg border">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="6" r="4"/><path stroke-linecap="round" d="M19.998 18c.002-.164.002-.331.002-.5c0-2.485-3.582-4.5-8-4.5s-8 2.015-8 4.5S4 22 12 22c2.231 0 3.84-.157 5-.437"/></g></svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900">{{ form.title || $t('Your Post Title') }}</h4>
                                    <p class="text-sm text-gray-600 mt-2 whitespace-pre-wrap">{{ form.content }}</p>
                                    <div class="flex gap-2 mt-3">
                                        <span v-for="platform in form.platforms" :key="platform" class="text-xs px-2 py-1 bg-gray-100 rounded-full">
                                            {{ getPlatformName(platform) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <Link href="/post-scheduler" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ $t('Cancel') }}
                        </Link>
                        <button 
                            type="submit" 
                            :disabled="form.processing || form.platforms.length === 0"
                            class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">{{ $t('Scheduling...') }}</span>
                            <span v-else>{{ $t('Schedule Post') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { ref, computed } from 'vue';
    import { trans } from 'laravel-vue-i18n';

    const availablePlatforms = ref([
        {
            id: 'facebook',
            name: 'Facebook',
            description: 'Share to Facebook'
        },
        {
            id: 'instagram',
            name: 'Instagram',
            description: 'Post to Instagram'
        },
        {
            id: 'tiktok',
            name: 'TikTok',
            description: 'Upload to TikTok'
        },
        {
            id: 'twitter',
            name: 'X (Twitter)',
            description: 'Tweet on X'
        },
        {
            id: 'linkedin',
            name: 'LinkedIn',
            description: 'Share on LinkedIn'
        }
    ]);

    const form = useForm({
        title: '',
        content: '',
        platforms: [],
        scheduled_at: '',
        media: []
    });

    const minDateTime = computed(() => {
        const now = new Date();
        now.setMinutes(now.getMinutes() + 5); // Minimum 5 minutes from now
        return now.toISOString().slice(0, 16);
    });

    const togglePlatform = (platformId) => {
        const index = form.platforms.indexOf(platformId);
        if (index > -1) {
            form.platforms.splice(index, 1);
        } else {
            form.platforms.push(platformId);
        }
    };

    const getPlatformName = (platformId) => {
        const platform = availablePlatforms.value.find(p => p.id === platformId);
        return platform ? platform.name : platformId;
    };

    const submit = () => {
        form.post('/post-scheduler', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
            },
        });
    };
</script>



