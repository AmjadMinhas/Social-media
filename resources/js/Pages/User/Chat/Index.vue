<template>
    <AppLayout v-slot:default="slotProps">
        <div class="md:flex md:flex-grow md:overflow-hidden">
            <div class="md:w-[30%] md:flex flex-col h-full bg-white border-r border-l" :class="contact ? 'hidden' : ''">
                <ChatTable :rows="rows" :filters="props.filters" :rowCount="props.rowCount" :ticketingIsEnabled="ticketingIsEnabled" :status="props?.status" :chatSortDirection="props.chat_sort_direction"/>
            </div>
            <div class="min-w-0 bg-cover flex flex-col chat-bg"  :class="contact ? 'h-screen md:w-[70%]' : 'md:h-screen md:w-[70%]'">
                <ChatHeader v-if="contact" :ticketingIsEnabled="ticketingIsEnabled" :contactGroups="contactGroups" :contact="contact" :displayContactInfo="displayContactInfo" :ticket="ticket" :addon="addon" @toggleView="toggleContactView" @deleteThread="deleteThread" @closeThread="closeThread"/>
                <div v-if="contact && !displayTemplate" class="flex-1 overflow-y-auto" ref="scrollContainer2">
                    <ChatThread v-if="!displayContactInfo && !loadingThread && !displayTemplate" :rows="chatThread" />
                    <Contact v-if="displayContactInfo && !displayTemplate" class="bg-white h-full" :fields="props.fields" :contact="contact" :locationSettings="props.locationSettings" />
                </div>
                <div v-if="contact && !displayContactInfo && !formLoading && !displayTemplate" class="w-full py-4">
                    <ChatForm :contact="contact" :simpleForm="simpleForm" :chatLimitReached="isChatLimitReached" @viewTemplate="displayTemplate = true;" />
                </div>
                <div v-if="displayTemplate" class="flex-1 overflow-y-hidden">
                    <CampaignForm v-if="displayTemplate" class="bg-white h-full" :contact="contact.uuid" :templates="templates" :contactGroups="[]" :settings="props.settings" :displayCancelBtn="false" :displayTitle="true" :isCampaignFlow="false" :scheduleTemplate="false" :sendText="'Send Message'" @viewTemplate="displayTemplate = false;"/>
                </div>
            </div>
            <!--<div v-if="contact" class="md:w-[25%] min-w-0 bg-cover flex flex-col bg-white border-l">
                <ChatContact v-if="contact" class="bg-white h-full" :contact="contact" />
            </div>-->
        </div>
        <button class="hidden" ref="toggleNavbarBtn" @click="slotProps.toggleNavBar"></button>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import axios from 'axios';
    import { router, useForm } from '@inertiajs/vue3';
    import { defineEmits, ref, onMounted, watch } from 'vue';
    import CampaignForm from '@/Components/CampaignForm.vue';
    import ChatForm from '@/Components/ChatComponents/ChatForm.vue';
    import ChatHeader from '@/Components/ChatComponents/ChatHeader.vue';
    import ChatTable from '@/Components/ChatComponents/ChatTable.vue';
    import ChatThread from '@/Components/ChatComponents/ChatThread.vue';
    import ChatContact from '@/Components/ChatComponents/ChatContact.vue';
    import Contact from '@/Components/ContactInfo.vue';
    import { getEchoInstance } from '../../../echo';

    const props = defineProps(['contactGroups','rows', 'rowCount', 'pusherSettings', 'organizationId', 'isChatLimitReached', 'toggleNavBar', 'state', 'demoNumber', 'settings', 'status', 'chatThread', 'addon', 'contact', 'ticket', 'chat_sort_direction', 'filters', 'templates', 'fields', 'locationSettings', 'simpleForm']);
    const rows = ref(props.rows);
    const rowCount = ref(props.rowCount);
    const scrollContainer = ref(null);
    const scrollContainer2 = ref(null);
    const loadingThread = ref(false);
    const displayContactInfo = ref(false);
    const displayTemplate = ref(false);
    const formLoading = ref(false);
    const isChatLimitReached = ref(props.isChatLimitReached);
    const toggleNavbarBtn = ref(null);
    const isOpen = ref(false);
    const config = ref(props.settings.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const ticketingIsEnabled = ref(settings.value?.tickets?.active ?? false);
    const queryParamsString = window.location.search;
    const chatThread = ref(props.chatThread);
    const contact = ref(props.contact);

    watch(() => props.rows, (newRows) => {
        rows.value = newRows;
        //rowCount.value = ref(props.rowCount);
    });

    const toggleDropdown = () => {
        isOpen.value = !isOpen.value;
    }

    function toggleContactView(value) {
        displayContactInfo.value = value;
    }

    const scrollToBottom = () => {
        const container = scrollContainer2.value;
        if (container) {
            container.scrollTo({
                top: container.scrollHeight,
                behavior: 'smooth',
            });
        }
    };

    const closeThread = () => {
        toggleNavbarBtn.value.click();
        contact.value = null;
    }

    const deleteThread = () => {
        chatThread.value = [];
        axios.delete('/chats/' + contact.value.uuid)
    }

    const updateChatThread = (chat) => {
        const wamId = chat[0].value.wam_id;
        const wamIdExists = chatThread.value.some(existingChat => existingChat[0].value.wam_id === wamId);

        if (!wamIdExists && chat[0].value.deleted_at == null) {
            chatThread.value.push(chat);
            setTimeout(scrollToBottom, 100);
        }
    }

    const updateSidePanel = async(chat) => {
        if(contact.value && contact.value.id == chat[0].value.contact_id){
            updateChatThread(chat);
        }

        const response = await axios.get('/chats');
        if (response) {
            rows.value = response.data.result;
        }
    }

    const onCloseDemoModal = () => {
        isDemoModalOpen.value = false;
    }

    onMounted(() => {
        const echo = getEchoInstance(
            props.pusherSettings['pusher_app_key'],
            props.pusherSettings['pusher_app_cluster']
        );

        echo.channel('chats.ch' + props.organizationId).listen('NewChatEvent', (event) => {
            updateSidePanel(event.chat);
        });

        scrollToBottom();
    });
</script>