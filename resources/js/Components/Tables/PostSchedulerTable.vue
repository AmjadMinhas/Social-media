<script setup>
    import { Link } from "@inertiajs/vue3";
    import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
    import debounce from 'lodash/debounce';
    import { router, useForm } from '@inertiajs/vue3';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        },
        connectedAccounts: {
            type: Object,
            default: () => ({})
        }
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const form = useForm({'test': null});

    const deleteAction = (key) => {
        form.delete('/post-scheduler/' + key);
    }
    
    const params = ref({
        search: props.filters?.search || '',
        status: props.filters?.status || null,
        platform: props.filters?.platform || null,
        date_from: props.filters?.date_from || null,
        date_to: props.filters?.date_to || null,
    });

    const isSearching = ref(false);
    const showDatePicker = ref(false);
    const selectedPlatforms = ref([]);

    // Date range - default to 6 months
    const today = new Date();
    const sixMonthsLater = new Date();
    sixMonthsLater.setMonth(today.getMonth() + 6);
    
    function formatDateForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    const dateFrom = ref(props.filters?.date_from || formatDateForInput(today));
    const dateTo = ref(props.filters?.date_to || formatDateForInput(sixMonthsLater));

    // Initialize selected platforms from filter
    if (props.filters?.platform && props.filters.platform.trim() !== '') {
        selectedPlatforms.value = props.filters.platform.split(',').map(p => p.trim()).filter(p => p);
    }

    // Watch for filter prop changes and sync with local state
    watch(() => props.filters, (newFilters) => {
        if (newFilters) {
            params.value.search = newFilters.search || '';
            params.value.status = newFilters.status || null;
            params.value.platform = newFilters.platform || null;
            params.value.date_from = newFilters.date_from || null;
            params.value.date_to = newFilters.date_to || null;
            
            if (newFilters.date_from) {
                dateFrom.value = newFilters.date_from;
            }
            if (newFilters.date_to) {
                dateTo.value = newFilters.date_to;
            }
            
            if (newFilters.platform && newFilters.platform.trim() !== '') {
                selectedPlatforms.value = newFilters.platform.split(',').map(p => p.trim()).filter(p => p);
            } else {
                selectedPlatforms.value = [];
            }
        }
    }, { deep: true, immediate: true });

    const clearSearch = () => {
        params.value.search = '';
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        const searchParams = {
            ...params.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            platform: selectedPlatforms.value.length > 0 ? selectedPlatforms.value.join(',') : null
        };
        
        router.visit('/post-scheduler', {
            method: 'get',
            data: searchParams,
            preserveState: true,
            preserveScroll: true,
        });
        
        setTimeout(() => {
            isSearching.value = false;
        }, 500);
    }

    const filterByStatus = (status) => {
        params.value.status = status;
        runSearch();
    }

    const togglePlatform = (platform) => {
        const index = selectedPlatforms.value.indexOf(platform);
        if (index > -1) {
            selectedPlatforms.value.splice(index, 1);
        } else {
            selectedPlatforms.value.push(platform);
        }
        runSearch();
    }

    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    const getStatusColor = (status) => {
        const colors = {
            'scheduled': 'bg-blue-100 text-blue-800',
            'publishing': 'bg-yellow-100 text-yellow-800',
            'published': 'bg-green-100 text-green-800',
            'failed': 'bg-red-100 text-red-800',
            'cancelled': 'bg-gray-100 text-gray-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    }

    const parsePlatforms = (platforms) => {
        if (Array.isArray(platforms)) {
            return platforms;
        }
        if (typeof platforms === 'string') {
            try {
                return JSON.parse(platforms);
            } catch (e) {
                return [];
            }
        }
        return [];
    }

    const getPlatformName = (platform) => {
        const names = {
            facebook: 'Facebook',
            instagram: 'Instagram',
            tiktok: 'TikTok',
            twitter: 'X (Twitter)',
            linkedin: 'LinkedIn'
        };
        return names[platform] || platform;
    }

    const getPlatformIcon = (platform) => {
        const icons = {
            facebook: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#1877F2" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>`,
            linkedin: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#0A66C2" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>`,
            instagram: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><defs><radialGradient id="instagramGradient" cx="30%" cy="110%"><stop offset="0%" style="stop-color:#fdf497" /><stop offset="5%" style="stop-color:#fdf497" /><stop offset="45%" style="stop-color:#fd5949" /><stop offset="60%" style="stop-color:#d6249f" /><stop offset="90%" style="stop-color:#285AEB" /></radialGradient></defs><path fill="url(#instagramGradient)" d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8A1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5a5 5 0 0 1-5 5a5 5 0 0 1-5-5a5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3a3 3 0 0 0 3 3a3 3 0 0 0 3-3a3 3 0 0 0-3-3z"/></svg>`,
            twitter: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18.205 2.25h3.308l-7.227 8.26l8.502 11.24H16.13l-5.214-6.817L4.95 21.75H1.64l7.73-8.835L1.215 2.25H8.04l4.713 6.231l5.45-6.231Zm-1.161 17.52h1.833L7.045 4.126H5.078L17.044 19.77Z"/></svg>`,
        };
        return icons[platform] || '';
    }

    const connectedPlatforms = computed(() => {
        return Object.keys(props.connectedAccounts || {});
    });

    const getPostType = (post) => {
        // Determine post type based on content/media
        if (post.media && post.media.length > 0) {
            return 'Post Composer';
        }
        return 'Post Composer';
    }

    const getMediaThumbnail = (media) => {
        if (!media || !Array.isArray(media) || media.length === 0) {
            return null;
        }
        return media[0];
    }

    const isVideo = (mediaUrl) => {
        if (!mediaUrl) return false;
        const videoExtensions = ['.mp4', '.mov', '.avi', '.webm', '.mkv'];
        const lowerUrl = mediaUrl.toLowerCase();
        return videoExtensions.some(ext => lowerUrl.includes(ext)) || 
               lowerUrl.includes('/video/') || 
               lowerUrl.includes('video');
    }

    const getThumbnailUrl = (mediaUrl) => {
        if (!mediaUrl) return null;
        // Try to get thumbnail URL by replacing the extension or appending _thumb
        if (isVideo(mediaUrl)) {
            // For videos, try to find thumbnail URL (e.g., video.mp4 -> video_thumb.jpg)
            const urlParts = mediaUrl.split('/');
            const fileName = urlParts[urlParts.length - 1];
            const nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
            
            // Try to find thumbnail in thumbnails directory
            const directory = mediaUrl.substring(0, mediaUrl.lastIndexOf('/'));
            const thumbnailUrl = directory + '/thumbnails/' + nameWithoutExt + '_thumb.jpg';
            return thumbnailUrl;
        }
        // For images, use the image URL directly as thumbnail
        return mediaUrl;
    }

    const handleImageError = (event) => {
        // If thumbnail fails to load, fall back to original image/video URL
        const thumbnailUrl = event.target.src;
        const originalUrl = thumbnailUrl.replace('/thumbnails/', '/').replace('_thumb.jpg', '');
        if (originalUrl !== thumbnailUrl) {
            event.target.src = originalUrl;
        }
    }

    const getPrevLink = () => {
        if (!props.rows?.links || props.rows.links.length === 0) return null;
        const prevLink = props.rows.links.find(link => link.label === '&laquo; Previous' || link.label === 'Previous');
        return prevLink?.url || null;
    }

    const getNextLink = () => {
        if (!props.rows?.links || props.rows.links.length === 0) return null;
        const nextLink = props.rows.links.find(link => link.label === 'Next &raquo;' || link.label === 'Next');
        return nextLink?.url || null;
    }

    // Real-time polling - refresh data every 30 seconds
    let pollingInterval = null;

    const refreshData = () => {
        const searchParams = {
            ...params.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            platform: selectedPlatforms.value.length > 0 ? selectedPlatforms.value.join(',') : null
        };
        
        // Remove null/empty values
        const filteredParams = Object.fromEntries(
            Object.entries(searchParams).filter(([_, value]) => value !== null && value !== '')
        );
        
        router.visit('/post-scheduler', {
            method: 'get',
            data: filteredParams,
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'filters'],
        });
    };

    onMounted(() => {
        // Start polling every 30 seconds for real-time updates
        pollingInterval = setInterval(() => {
            refreshData();
        }, 30000); // 30 seconds
    });

    onUnmounted(() => {
        // Clear polling interval when component is unmounted
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    });
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header with Title and New Post Button -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
    <div>
                <h2 class="text-2xl font-semibold text-gray-900">{{ $t('Social Planner') }}</h2>
            </div>
            <div class="flex items-center gap-3">
                <!-- Settings Icon -->
                <button @click="router.visit('/social-accounts')" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition" :title="$t('Manage Social Accounts')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
                    </svg>
                </button>
                
                <!-- New Post Button with Dropdown -->
                <Dropdown align="right">
                    <button class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        {{ $t('New Post') }}
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem as="div">
                                <Link href="/post-scheduler/create" class="flex items-center w-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    {{ $t('Create New Post') }}
                                </Link>
                            </DropdownItem>
                            <DropdownItem>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="9" y1="3" x2="9" y2="21"></line>
                                    </svg>
                                    {{ $t('Social Planner Templates') }}
                                </div>
                            </DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>
            </div>
            </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 px-6">
            <div class="flex space-x-8">
                <button class="px-1 py-4 text-sm font-medium text-blue-600 border-b-2 border-blue-600">
                    {{ $t('Planner') }}
                </button>
                <button class="px-1 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                    {{ $t('Content') }}
                </button>
                <button class="px-1 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                    {{ $t('Statistics') }}
                </button>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap items-center gap-4">
                <!-- Platform Icons -->
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button 
                            v-for="platform in connectedPlatforms" 
                            :key="platform"
                            @click="togglePlatform(platform)"
                            :class="selectedPlatforms.includes(platform) ? 'ring-2 ring-blue-500' : 'opacity-50 hover:opacity-100'"
                            class="p-1.5 rounded-md transition"
                            :title="getPlatformName(platform)"
                        >
                            <span v-html="getPlatformIcon(platform)"></span>
                        </button>
                    </div>
                    <span v-if="connectedPlatforms.length > 3" class="text-xs text-gray-500 ml-1">
                        +{{ connectedPlatforms.length - 3 }}
                                </span>
                            </div>

                <!-- Date Range Picker -->
                <div class="flex items-center gap-2 border border-gray-300 rounded-md px-3 py-1.5 bg-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-500">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <input 
                        type="date" 
                        v-model="dateFrom" 
                        @change="runSearch"
                        class="border-0 p-0 text-sm focus:ring-0 focus:outline-0 w-32"
                    />
                    <span class="text-gray-400">to</span>
                    <input 
                        type="date" 
                        v-model="dateTo" 
                        @change="runSearch"
                        class="border-0 p-0 text-sm focus:ring-0 focus:outline-0 w-32"
                    />
                </div>

                <!-- Actions Dropdown -->
                <Dropdown align="left">
                    <button class="flex items-center gap-2 px-3 py-1.5 border border-gray-300 rounded-md bg-white text-sm text-gray-700 hover:bg-gray-50">
                        {{ $t('Actions') }}
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem>{{ $t('Bulk Actions') }}</DropdownItem>
                            <DropdownItem>{{ $t('Export') }}</DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>

                <!-- Filter Views Dropdown -->
                <Dropdown align="left">
                    <button class="flex items-center gap-2 px-3 py-1.5 border border-gray-300 rounded-md bg-white text-sm text-gray-700 hover:bg-gray-50">
                        {{ $t('Filter Views') }}: {{ params.status ? $t(params.status.charAt(0).toUpperCase() + params.status.slice(1)) : $t('All') }}
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem @click="filterByStatus(null)">{{ $t('All') }}</DropdownItem>
                            <DropdownItem @click="filterByStatus('scheduled')">{{ $t('Scheduled') }}</DropdownItem>
                            <DropdownItem @click="filterByStatus('published')">{{ $t('Published') }}</DropdownItem>
                            <DropdownItem @click="filterByStatus('failed')">{{ $t('Failed') }}</DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>

                <!-- Search Bar -->
                <div class="flex-1 min-w-[200px] max-w-md">
                    <div class="relative">
                        <input 
                            @input="search" 
                            v-model="params.search" 
                            type="text" 
                            class="w-full pl-10 pr-10 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="$t('Search by caption')"
                        >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <button 
                            v-if="params.search" 
                            @click="clearSearch" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('Caption') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('Media') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('Status') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('Type') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $t('Date') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(item, index) in (props.rows?.data || [])" :key="index" class="hover:bg-gray-50">
                        <!-- Caption -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Link :href="'/post-scheduler/' + item.uuid" class="block">
                                <div class="text-sm text-gray-900 max-w-xs truncate">
                                    {{ item.content || item.title || '-' }}
                            </div>
                        </Link>
                        </td>
                        
                        <!-- Media -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Link :href="'/post-scheduler/' + item.uuid" class="block">
                                <div v-if="getMediaThumbnail(item.media)" class="w-12 h-12 rounded-md overflow-hidden bg-gray-100 relative">
                                    <img 
                                        :src="getThumbnailUrl(getMediaThumbnail(item.media)) || getMediaThumbnail(item.media)" 
                                        :alt="item.title" 
                                        class="w-full h-full object-cover"
                                        @error="handleImageError"
                                    >
                                    <!-- Play icon overlay for videos -->
                                    <div v-if="isVideo(getMediaThumbnail(item.media))" class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white" class="drop-shadow-lg">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div v-else class="w-12 h-12 rounded-md bg-gray-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                </div>
                        </Link>
                        </td>
                        
                        <!-- Status -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Link :href="'/post-scheduler/' + item.uuid" class="block">
                                <span :class="getStatusColor(item.status)" class="px-2.5 py-1 rounded-full text-xs font-medium inline-block">
                                {{ item.status_label }}
                            </span>
                        </Link>
                        </td>
                        
                        <!-- Type -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Link :href="'/post-scheduler/' + item.uuid" class="block">
                                <span class="text-sm text-gray-900">{{ getPostType(item) }}</span>
                            </Link>
                        </td>
                        
                        <!-- Date -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Link :href="'/post-scheduler/' + item.uuid" class="block">
                                <div class="text-sm text-gray-900">{{ formatDate(item.scheduled_at) }}</div>
                            </Link>
                        </td>
                        
                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <Dropdown align="right">
                                <button class="text-gray-400 hover:text-gray-600 p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16">
                                        <path fill="currentColor" d="M9.5 13a1.5 1.5 0 1 1-3 0a1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0a1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0a1.5 1.5 0 0 1 3 0z"/>
                                    </svg>
                                </button>
                                <template #items>
                                    <DropdownItemGroup>
                                        <DropdownItem as="button" @click="openAlert($t('Are you sure you want to delete?'), deleteAction, [item.uuid])">
                                            <div class="flex items-center space-x-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                                <span>{{ $t('Delete') }}</span>
                                            </div>
                                        </DropdownItem>
                                    </DropdownItemGroup>
                                </template>
                            </Dropdown>
                        </td>
                    </tr>
                    
                    <!-- Empty State -->
                    <tr v-if="!props.rows?.data || props.rows.data.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-gray-400 mb-3">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <p class="text-gray-500 text-sm">{{ $t('No scheduled posts found') }}</p>
                            <Link href="/post-scheduler/create" class="mt-3 inline-block text-blue-600 hover:text-blue-800 text-sm">
                                {{ $t('Create your first post') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="props.rows?.data && props.rows.data.length > 0 && props.rows.total > props.rows.per_page" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    {{ $t('Showing') }} 
                    <span class="font-medium">{{ props.rows.from || (props.rows.data ? 1 : 0) }}</span>
                    {{ $t('to') }} 
                    <span class="font-medium">{{ props.rows.to || (props.rows.data ? props.rows.data.length : 0) }}</span>
                    {{ $t('of') }} 
                    <span class="font-medium">{{ props.rows.total || (props.rows.data ? props.rows.data.length : 0) }}</span>
                    {{ $t('result(s)') }}
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        v-if="props.rows?.links && props.rows.links.length > 0 && getPrevLink()"
                        @click="router.visit(getPrevLink())"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white hover:bg-gray-50"
                    >
                        {{ $t('Previous') }}
                    </button>
                    <span v-if="props.rows?.current_page" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md">
                        {{ props.rows.current_page }}
                    </span>
                    <button 
                        v-if="props.rows?.links && props.rows.links.length > 0 && getNextLink()"
                        @click="router.visit(getNextLink())"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white hover:bg-gray-50"
                    >
                        {{ $t('Next') }}
                    </button>
                </div>
            </div>
        </div>

        <AlertModal :isOpen="isOpenAlert" @confirm="confirmAlert" />
    </div>
</template>
