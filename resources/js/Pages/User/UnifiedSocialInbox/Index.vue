<template>
    <AppLayout v-slot:default="slotProps">
        <div class="md:flex md:flex-grow md:overflow-hidden">
            <!-- Left Sidebar - Contacts List -->
            <div class="md:w-[30%] md:flex flex-col h-full bg-white border-r border-l" :class="selectedContact ? 'hidden md:hidden' : ''">
                <!-- Header with Stats and Sync Button -->
                <div class="p-4 border-b bg-white">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold">Unified Social Inbox</h2>
                        <button 
                            @click="syncMessages" 
                            :disabled="syncing"
                            class="px-3 py-1.5 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600 disabled:opacity-50"
                        >
                            <span v-if="!syncing">Sync</span>
                            <span v-else>Syncing...</span>
                        </button>
                    </div>
                    
                    <!-- Platform Stats -->
                    <div class="grid grid-cols-4 gap-2 mb-3">
                        <div class="text-center p-2 bg-green-50 rounded">
                            <div class="text-xs text-gray-600">WhatsApp</div>
                            <div class="font-bold text-green-600">{{ stats.whatsapp || 0 }}</div>
                        </div>
                        <div class="text-center p-2 bg-blue-50 rounded">
                            <div class="text-xs text-gray-600">Facebook</div>
                            <div class="font-bold text-blue-600">{{ stats.facebook || 0 }}</div>
                        </div>
                        <div class="text-center p-2 bg-purple-50 rounded">
                            <div class="text-xs text-gray-600">Instagram</div>
                            <div class="font-bold text-purple-600">{{ stats.instagram || 0 }}</div>
                        </div>
                        <div class="text-center p-2 bg-sky-50 rounded">
                            <div class="text-xs text-gray-600">Twitter</div>
                            <div class="font-bold text-sky-600">{{ stats.twitter || 0 }}</div>
                        </div>
                    </div>

                    <!-- Platform Filter -->
                    <select 
                        v-model="selectedPlatform" 
                        @change="filterByPlatform"
                        class="w-full px-3 py-2 border rounded-md text-sm mb-3"
                    >
                        <option value="">All Platforms</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="facebook">Facebook</option>
                        <option value="instagram">Instagram</option>
                        <option value="twitter">Twitter</option>
                    </select>

                    <!-- Search -->
                    <input 
                        type="text" 
                        v-model="searchTerm"
                        @input="searchContacts"
                        placeholder="Search contacts..."
                        class="w-full px-3 py-2 border rounded-md text-sm"
                    />
                </div>

                <!-- Contacts List -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="contacts && contacts.data && contacts.data.length > 0">
                        <div 
                            v-for="contact in contacts.data" 
                            :key="contact.id"
                            @click="selectContact(contact)"
                            class="p-3 border-b hover:bg-gray-50 cursor-pointer"
                            :class="selectedContact && selectedContact.id === contact.id ? 'bg-blue-50' : ''"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-medium">{{ contact.full_name || contact.first_name + ' ' + contact.last_name || 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span v-if="contact.last_chat">{{ getLastMessage(contact.last_chat) }}</span>
                                        <span v-else-if="contact.lastChat">{{ getLastMessage(contact.lastChat) }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <div class="flex gap-1 mb-1">
                                        <span 
                                            v-for="platform in getContactPlatforms(contact)" 
                                            :key="platform"
                                            :class="getPlatformBadgeClass(platform)"
                                            class="text-xs px-2 py-0.5 rounded"
                                        >
                                            {{ platform }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ contact.latest_chat_created_at ? formatDate(contact.latest_chat_created_at) : '' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-4 text-center text-gray-500">
                        <p class="mb-2">No contacts found</p>
                        <p class="text-xs">Connect social accounts and sync messages to get started</p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="contacts && contacts.last_page > 1" class="p-4">
                        <Pagination :pagination="paginationData" />
                    </div>
                </div>
            </div>

            <!-- Right Side - Messages -->
            <div class="min-w-0 bg-cover flex flex-col chat-bg" :class="selectedContact ? 'h-screen md:w-[70%]' : 'md:h-screen md:w-[70%]'">
                <!-- Contact Header -->
                <div v-if="selectedContact" class="p-4 bg-white border-b flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <button @click="closeContact" class="md:hidden p-2 hover:bg-gray-100 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 18l-6-6l6-6"/></svg>
                        </button>
                        <div>
                            <div class="font-semibold">{{ selectedContact.full_name || 'Unknown' }}</div>
                            <div class="text-xs text-gray-500">
                                <span 
                                    v-for="platform in getContactPlatforms(selectedContact)" 
                                    :key="platform"
                                    :class="getPlatformBadgeClass(platform)"
                                    class="text-xs px-2 py-0.5 rounded mr-1"
                                >
                                    {{ platform }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages Thread -->
                <div v-if="selectedContact && messages" class="flex-1 overflow-y-auto p-4" ref="messagesContainer">
                    <div v-if="messages.data && messages.data.length > 0">
                        <div 
                            v-for="message in messages.data" 
                            :key="message.id"
                            class="mb-4"
                        >
                            <div class="flex" :class="message.type === 'outbound' ? 'justify-end' : 'justify-start'">
                                <div 
                                    class="max-w-[70%] rounded-lg p-3"
                                    :class="message.type === 'outbound' ? 'bg-blue-500 text-white' : 'bg-white border'"
                                >
                                    <div class="flex items-center gap-2 mb-1">
                                        <span 
                                            :class="getPlatformBadgeClass(message.platform)"
                                            class="text-xs px-2 py-0.5 rounded"
                                        >
                                            {{ message.platform }}
                                        </span>
                                        <span class="text-xs opacity-70">
                                            {{ formatDate(message.created_at) }}
                                        </span>
                                    </div>
                                    <div>{{ getMessageText(message) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center text-gray-500 mt-8">
                        No messages yet
                    </div>
                </div>

                <!-- Message Form -->
                <div v-if="selectedContact" class="p-4 bg-white border-t">
                    <form @submit.prevent="sendMessage" class="flex gap-2">
                        <select 
                            v-model="selectedSendPlatform" 
                            class="px-3 py-2 border rounded-md text-sm"
                            required
                        >
                            <option value="">Select Platform</option>
                            <option 
                                v-for="platform in getContactPlatforms(selectedContact)" 
                                :key="platform"
                                :value="platform"
                            >
                                {{ platform }}
                            </option>
                        </select>
                        <input 
                            type="text" 
                            v-model="messageText"
                            placeholder="Type a message..."
                            class="flex-1 px-3 py-2 border rounded-md"
                            required
                        />
                        <button 
                            type="submit"
                            :disabled="!selectedSendPlatform || !messageText || sending"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
                        >
                            <span v-if="!sending">Send</span>
                            <span v-else>Sending...</span>
                        </button>
                    </form>
                </div>

                <!-- Empty State -->
                <div v-if="!selectedContact" class="flex-1 flex items-center justify-center text-gray-500">
                    <div class="text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" class="mx-auto mb-4 opacity-50">
                            <path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                        </svg>
                        <p>Select a contact to view messages</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from "../Layout/App.vue";
import Pagination from '@/Components/Pagination.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch, onMounted, nextTick, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    contacts: {
        type: Object,
        default: () => ({ data: [], links: [], current_page: 1, last_page: 1, per_page: 20, total: 0 })
    },
    messages: {
        type: Object,
        default: null
    },
    selectedContact: {
        type: Object,
        default: null
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    stats: {
        type: Object,
        default: () => ({ whatsapp: 0, facebook: 0, instagram: 0, twitter: 0, total: 0 })
    },
    connectedPlatforms: {
        type: Array,
        default: () => []
    },
});

const selectedContact = ref(props.selectedContact || null);
const messages = ref(props.messages || null);
const contacts = ref(props.contacts || { data: [], links: [], current_page: 1, last_page: 1, per_page: 20, total: 0 });
const stats = ref(props.stats || { whatsapp: 0, facebook: 0, instagram: 0, twitter: 0, total: 0 });
const searchTerm = ref(props.filters?.search || '');
const selectedPlatform = ref(props.filters?.platform || '');
const selectedSendPlatform = ref('');
const messageText = ref('');
const sending = ref(false);
const syncing = ref(false);
const messagesContainer = ref(null);

// Transform contacts data for Pagination component
const paginationData = computed(() => {
    if (!contacts.value) {
        return { current_page: 1, last_page: 1, total: 0 };
    }
    return {
        current_page: contacts.value.current_page || 1,
        last_page: contacts.value.last_page || 1,
        total: contacts.value.total || 0,
    };
});

const getContactPlatforms = (contact) => {
    if (!contact || !contact.chats) return [];
    const platforms = new Set();
    contact.chats.forEach(chat => {
        if (chat.platform) platforms.add(chat.platform);
    });
    return Array.from(platforms);
};

const getPlatformBadgeClass = (platform) => {
    const classes = {
        'whatsapp': 'bg-green-100 text-green-700',
        'facebook': 'bg-blue-100 text-blue-700',
        'instagram': 'bg-purple-100 text-purple-700',
        'twitter': 'bg-sky-100 text-sky-700',
    };
    return classes[platform] || 'bg-gray-100 text-gray-700';
};

const getLastMessage = (chat) => {
    if (!chat || !chat.metadata) return '';
    try {
        const metadata = JSON.parse(chat.metadata);
        if (metadata.text && metadata.text.body) {
            return metadata.text.body.substring(0, 50);
        }
        return 'Media message';
    } catch {
        return '';
    }
};

const getMessageText = (message) => {
    if (!message || !message.metadata) return '';
    try {
        const metadata = JSON.parse(message.metadata);
        if (metadata.text && metadata.text.body) {
            return metadata.text.body;
        }
        return 'Media message';
    } catch {
        return '';
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString();
};

const selectContact = (contact) => {
    selectedContact.value = contact;
    router.get(`/social-inbox/${contact.uuid}`, {
        platform: selectedPlatform.value,
        search: searchTerm.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const closeContact = () => {
    selectedContact.value = null;
    router.get('/social-inbox', {
        platform: selectedPlatform.value,
        search: searchTerm.value,
    });
};

const filterByPlatform = () => {
    router.get('/social-inbox', {
        platform: selectedPlatform.value,
        search: searchTerm.value,
    }, {
        preserveState: true,
    });
};

const searchContacts = () => {
    router.get('/social-inbox', {
        platform: selectedPlatform.value,
        search: searchTerm.value,
    }, {
        preserveState: true,
    });
};

const sendMessage = async () => {
    if (!selectedSendPlatform.value || !messageText.value) return;
    
    sending.value = true;
    
    try {
        const form = useForm({
            contact_id: selectedContact.value.id,
            platform: selectedSendPlatform.value,
            message: messageText.value,
        });

        form.post('/social-inbox/send', {
            preserveScroll: true,
            onSuccess: () => {
                messageText.value = '';
                // Reload messages
                router.reload({ only: ['messages'] });
            },
            onFinish: () => {
                sending.value = false;
            },
        });
    } catch (error) {
        console.error('Error sending message:', error);
        sending.value = false;
    }
};

const syncMessages = async () => {
    syncing.value = true;
    
    try {
        const form = useForm({});
        form.post('/social-inbox/sync', {
            preserveScroll: true,
            onSuccess: () => {
                router.reload();
            },
            onFinish: () => {
                syncing.value = false;
            },
        });
    } catch (error) {
        console.error('Error syncing messages:', error);
        syncing.value = false;
    }
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

watch(() => props.messages, (newMessages) => {
    messages.value = newMessages;
    scrollToBottom();
});

watch(() => props.selectedContact, (newContact) => {
    selectedContact.value = newContact;
    if (newContact) {
        const platforms = getContactPlatforms(newContact);
        if (platforms.length > 0) {
            selectedSendPlatform.value = platforms[0];
        }
    }
});

onMounted(() => {
    console.log('UnifiedSocialInbox mounted', {
        contacts: contacts.value,
        stats: stats.value,
        selectedContact: selectedContact.value
    });
    scrollToBottom();
});
</script>

