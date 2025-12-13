<script setup>
    const props = defineProps({
        label: String,
        isOpen: Boolean,
        closeBtn: Boolean,
        showHeader: {
            type: Boolean,
            default: true
        }
    })

    const emit = defineEmits(['close']);

    function closeModal() {
        emit('close', true);
    }
</script>
<template>
    <div v-if="props.isOpen" class="fixed inset-0 z-[9999] overflow-y-auto" @click.self="closeModal">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal" style="z-index: -1;"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-10" @click.stop>
                <!-- Header -->
                <div v-if="props.showHeader != false" class="bg-white px-6 pt-6 pb-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            {{ props.label }}
                        </h3>
                        <button 
                            v-if="closeBtn === true" 
                            @click="closeModal" 
                            type="button"
                            class="text-gray-400 hover:text-gray-500 focus:outline-none"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Body -->
                <div class="bg-white px-6 pb-4">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>