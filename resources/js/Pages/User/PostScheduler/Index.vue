<template>
    <AppLayout>
        <div class="bg-white md:bg-inherit pt-0 px-4 md:pt-8 md:p-8 rounded-[5px] text-[#000] overflow-y-scroll">
            <div class="md:flex justify-between mt-8 md:mt-0 hidden">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Post Scheduler') }}</h2>
                    <p class="mb-6 md:flex items-center text-sm leading-6 text-gray-600 hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ml-1 mt-1">{{ $t('Schedule posts for multiple social media platforms') }}</span>
                    </p>
                </div>
                
            </div>

            <!-- Table Component-->
            <PostSchedulerTable 
                v-if="rows && rows.data !== undefined" 
                :rows="rows" 
                :filters="filters || {}" 
                :connectedAccounts="connectedAccounts || {}"
            />
            <div v-else class="p-8 text-center text-gray-500">
                <p>Loading...</p>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import PostSchedulerTable from '@/Components/Tables/PostSchedulerTable.vue';

    const props = defineProps(['rows', 'filters', 'connectedAccounts']);
</script>



