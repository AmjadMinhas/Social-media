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

                    <!-- Publish Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('Publish Type') }} <span class="text-red-500">*</span>
                        </label>
                        <select 
                            v-model="form.publish_type" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-primary focus:border-primary"
                            required
                        >
                            <option value="scheduled">{{ $t('Schedule for later') }}</option>
                            <option value="now">{{ $t('Publish now') }}</option>
                        </select>
                        <div v-if="form.errors.publish_type" class="text-red-500 text-sm mt-1">{{ form.errors.publish_type }}</div>
                    </div>

                    <!-- Scheduled Date & Time (only show if scheduled) -->
                    <div v-if="form.publish_type === 'scheduled'">
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

                    <!-- Media Upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $t('Media (Optional)') }}
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                            <input 
                                type="file" 
                                multiple 
                                accept="image/*,video/mp4,video/quicktime,video/x-msvideo"
                                @change="handleMediaUpload"
                                class="hidden"
                                ref="mediaInput"
                                id="media-upload"
                            />
                            <div class="text-center">
                                <label for="media-upload" class="cursor-pointer inline-block">
                                    <div class="cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600">{{ $t('Click to upload media') }}</p>
                                        <p class="text-xs text-gray-500">{{ $t('Images (JPEG, PNG, GIF, WebP) or Videos (MP4, MOV, AVI)') }}</p>
                                        <p class="text-xs text-gray-400 mt-1">{{ $t('Max: 10MB for images, 100MB for videos') }}</p>
                                    </div>
                                </label>
                            </div>
                            
                            <!-- Media Preview -->
                            <div v-if="mediaPreview.length > 0" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div v-for="(preview, index) in mediaPreview" :key="index" class="relative group">
                                    <div class="aspect-square rounded-lg overflow-hidden border border-gray-300">
                                        <img v-if="preview.file.type.startsWith('image/')" :src="preview.url" :alt="preview.name" class="w-full h-full object-cover">
                                        <video v-else :src="preview.url" class="w-full h-full object-cover" controls></video>
                                    </div>
                                    <button 
                                        @click="removeMedia(index)"
                                        type="button"
                                        class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                    <p class="text-xs text-gray-500 mt-1 truncate">{{ preview.name }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="form.errors.media" class="text-red-500 text-sm mt-1">{{ form.errors.media }}</div>
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
                            type="button"
                            @click="submit"
                            :disabled="form.processing || form.platforms.length === 0"
                            class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">{{ $t('Scheduling...') }}</span>
                            <span v-else>{{ $t('Schedule Post') }}</span>
                        </button>
                        <div v-if="form.processing" class="text-sm text-gray-500 mt-2">
                            Processing... Please wait
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link, useForm, usePage, router } from "@inertiajs/vue3";
    import { ref, computed } from 'vue';
    import { trans } from 'laravel-vue-i18n';

    const mediaInput = ref(null);
    const page = usePage();

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
        publish_type: 'scheduled', // 'now', 'scheduled', or 'time_range'
        scheduled_at: '',
        scheduled_from: '',
        scheduled_to: '',
        media: []
    });

    const selectedMediaFiles = ref([]);
    const mediaPreview = ref([]);

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

    const handleMediaUpload = (event) => {
        console.log('Media upload triggered', event);
        
        // Get files from the input
        const input = event.target;
        if (!input || !input.files || input.files.length === 0) {
            console.log('No files selected');
            return;
        }
        
        const files = Array.from(input.files);
        console.log('Files selected:', files.length);
        
        // Process each file
        files.forEach(file => {
            console.log('Processing file:', file.name, file.type, file.size);
            
            // Check for duplicate files (same name and size)
            const isDuplicate = selectedMediaFiles.value.some(existingFile => 
                existingFile.name === file.name && 
                existingFile.size === file.size &&
                existingFile.lastModified === file.lastModified
            );
            
            if (isDuplicate) {
                console.log('File already selected, skipping:', file.name);
                return;
            }
            
            // Validate file type
            const validTypes = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/avi', 'video/webm'
            ];
            const validExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.mp4', '.mov', '.avi', '.webm'];
            
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            const isValidType = validTypes.includes(file.type) || validExtensions.includes(fileExtension);
            
            if (!isValidType) {
                alert(`${file.name}: Invalid file type. Please upload images (JPEG, PNG, GIF, WebP) or videos (MP4, MOV, AVI)`);
                return;
            }
            
            // Validate file size (max 10MB for images, 100MB for videos)
            const maxSize = file.type.startsWith('video/') || ['.mp4', '.mov', '.avi', '.webm'].includes(fileExtension)
                ? 100 * 1024 * 1024 
                : 10 * 1024 * 1024;
            if (file.size > maxSize) {
                alert(`${file.name}: File too large. Max size: ${file.type.startsWith('video/') ? '100MB' : '10MB'}`);
                return;
            }
            
            // Add file to selected files
            selectedMediaFiles.value.push(file);
            console.log('File added to selectedMediaFiles:', selectedMediaFiles.value.length);
            
            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                // Check if preview already exists for this file
                const previewExists = mediaPreview.value.some(preview => 
                    preview.name === file.name && 
                    preview.file.size === file.size &&
                    preview.file.lastModified === file.lastModified
                );
                
                if (!previewExists) {
                    mediaPreview.value.push({
                        file: file,
                        url: e.target.result,
                        name: file.name
                    });
                    console.log('Preview added:', mediaPreview.value.length);
                }
            };
            reader.onerror = (error) => {
                console.error('Error reading file:', error);
            };
            reader.readAsDataURL(file);
        });
        
        // Reset input value AFTER processing to allow selecting same file again
        // Use nextTick to ensure the change event has fully processed
        setTimeout(() => {
            if (input) {
                input.value = '';
            }
        }, 100);
    };

    const removeMedia = (index) => {
        selectedMediaFiles.value.splice(index, 1);
        mediaPreview.value.splice(index, 1);
    };

    const uploadMediaFiles = async () => {
        if (selectedMediaFiles.value.length === 0) {
            return [];
        }

        const uploadedUrls = [];
        const page = usePage();
        const csrfToken = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        for (const file of selectedMediaFiles.value) {
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('type', 'post_scheduler');

                const response = await fetch('/post-scheduler/upload-media', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: formData
                });

                // Check if response is JSON before parsing
                const contentType = response.headers.get('content-type');
                const isJson = contentType && contentType.includes('application/json');

                if (response.ok) {
                    if (isJson) {
                        const data = await response.json();
                        if (data.url || data.path) {
                            uploadedUrls.push(data.url || data.path);
                        }
                    } else {
                        const text = await response.text();
                        console.error('Non-JSON response received:', text.substring(0, 200));
                        throw new Error('Server returned non-JSON response');
                    }
                } else {
                    if (isJson) {
                        const errorData = await response.json();
                        console.error('Failed to upload:', file.name, errorData);
                        throw new Error(errorData.message || 'Upload failed');
                    } else {
                        const text = await response.text();
                        console.error('Non-JSON error response:', text.substring(0, 200));
                        throw new Error(`Upload failed with status ${response.status}`);
                    }
                }
            } catch (error) {
                console.error('Error uploading file:', file.name, error);
                // Don't silently fail - throw to stop the process
                throw error;
            }
        }

        return uploadedUrls;
    };

    const submit = async (e) => {
        if (e) {
            e.preventDefault();
        }
        
        console.log('Form submit triggered');
        console.log('Form data before validation:', {
            title: form.title,
            content: form.content,
            platforms: form.platforms,
            publish_type: form.publish_type,
            scheduled_at: form.scheduled_at,
            media_files_count: selectedMediaFiles.value.length
        });
        
        // Validate required fields
        if (!form.title || form.title.trim() === '') {
            alert('Please enter a post title');
            return;
        }
        
        if (!form.content || form.content.trim() === '') {
            alert('Please enter post content');
            return;
        }
        
        // Validate platforms
        if (form.platforms.length === 0) {
            alert('Please select at least one platform');
            return;
        }
        
        // Ensure publish_type is set
        if (!form.publish_type) {
            form.publish_type = form.scheduled_at ? 'scheduled' : 'now';
        }

        // Validate publish_type and scheduled_at
        if (form.publish_type === 'scheduled' && !form.scheduled_at) {
            alert('Please select a scheduled date and time');
            return;
        }
        
        console.log('Form data after validation:', {
            title: form.title,
            content: form.content,
            platforms: form.platforms,
            publish_type: form.publish_type,
            scheduled_at: form.scheduled_at,
        });

        // Upload media files first if any
        if (selectedMediaFiles.value.length > 0) {
            console.log('Uploading media files...', selectedMediaFiles.value.length);
            try {
                const uploadedUrls = await uploadMediaFiles();
                console.log('Media uploaded successfully:', uploadedUrls);
                
                // Ensure form.media is set as an array
                if (uploadedUrls && uploadedUrls.length > 0) {
                    form.media = uploadedUrls;
                    console.log('Form media set to:', form.media);
                } else {
                    console.warn('No media URLs returned from upload');
                    form.media = [];
                }
            } catch (error) {
                console.error('Error uploading media:', error);
                alert('Failed to upload some media files. Please try again.');
                return;
            }
        } else {
            form.media = [];
            console.log('No media files to upload');
        }
        
        // Final check before submission
        console.log('Final form.media before submission:', form.media);
        console.log('Form.media type:', typeof form.media, Array.isArray(form.media));

        // Ensure publish_type is set (default to 'scheduled' if not set)
        if (!form.publish_type) {
            form.publish_type = form.scheduled_at ? 'scheduled' : 'now';
        }

        console.log('Final form data before submission:', {
            title: form.title,
            content: form.content,
            platforms: form.platforms,
            publish_type: form.publish_type,
            scheduled_at: form.scheduled_at,
            media: form.media,
            media_count: form.media?.length || 0
        });

        // Debug: Log form state
        console.log('=== FORM SUBMISSION DEBUG ===');
        console.log('form.title:', form.title, typeof form.title);
        console.log('form.content:', form.content, typeof form.content);
        console.log('form.platforms:', form.platforms, Array.isArray(form.platforms));
        console.log('form.publish_type:', form.publish_type);
        console.log('form.data():', form.data());
        console.log('form object keys:', Object.keys(form));
        
        // Validate required fields
        if (!form.title || String(form.title).trim() === '') {
            alert('Please enter a post title');
            return;
        }
        
        if (!form.content || String(form.content).trim() === '') {
            alert('Please enter post content');
            return;
        }
        
        if (!form.platforms || !Array.isArray(form.platforms) || form.platforms.length === 0) {
            alert('Please select at least one platform');
            return;
        }
        
        // Ensure publish_type is set
        if (!form.publish_type) {
            form.publish_type = form.scheduled_at ? 'scheduled' : 'now';
        }
        
        console.log('=== SUBMITTING FORM ===');
        console.log('Final form.data():', form.data());
        
        // Submit form directly
        form.post('/post-scheduler', {
            preserveScroll: true,
            onStart: () => {
                console.log('Form submission started');
                console.log('Form data being sent:', form.data());
            },
            onProgress: (progress) => {
                console.log('Form submission progress:', progress);
            },
            onSuccess: (page) => {
                console.log('Form submitted successfully!', page);
                form.reset();
                selectedMediaFiles.value = [];
                mediaPreview.value = [];
            },
            onError: (errors) => {
                console.error('Form submission errors:', errors);
                console.error('Full error object:', JSON.stringify(errors, null, 2));
                console.error('Form data that was sent:', form.data());
                
                let errorMessage = 'Please check the form and try again.';
                
                if (errors.platforms) {
                    errorMessage = 'Please select at least one platform';
                } else if (errors.scheduled_at) {
                    errorMessage = 'Please select a valid scheduled date and time';
                } else if (errors.title) {
                    errorMessage = errors.title;
                } else if (errors.content) {
                    errorMessage = errors.content;
                } else if (errors.publish_type) {
                    errorMessage = errors.publish_type;
                }
                
                alert(errorMessage);
            },
            onFinish: () => {
                console.log('Form submission finished');
            }
        });
    };
</script>



