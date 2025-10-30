  <template>
    <AppLayout>
      <div class="p-6 ">
        <h1 class="text-3xl font-bold mb-6">{{ title }}</h1>

        <ul class="space-y-4">
          <li
            v-for="org in organization"
            :key="org.uuid"
            class="border rounded-md p-4 flex justify-between items-center bg-white shadow-sm"
          >
            <div>
              <h3 class="text-xl font-semibold">{{ org.name }}</h3>
              <p class="text-gray-600"> {{ org.subscription?.plan?.name }}</p>
            </div>
            <div class="space-x-2 flex-shrink-0">
              <button
                @click="openEditModal(org)"
                class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 transition"
              >
                تعديل
              </button>
              <button
                v-if="org.uuid !== firstOrganizationId"
                @click="destroy(org.uuid)"
                class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition"
              >
                حذف
              </button>

          </div>
          </li>
        </ul>


  <Modal :label="$t('Create transaction')" :isOpen=isOpenFormModal>
          <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
              <form @submit.prevent="submitForm" class="">

                  <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6 sm:col-span-4">
                            <FormInput v-model="form.name" label="اسم المنظمة" :class="'sm:col-span-6'" />

                  </div>
                  <div class="mt-6 flex">
                      <button type="button" @click="closeModal()" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 mr-4">{{ $t('Cancel') }}</button>
                      <button 
                      type="submit"
                          :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': isLoading }]"
                          :disabled="isLoading">
                          <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                          <span v-else>{{ $t('Save') }}</span>
                      </button>
                  </div>
              </form>
          </div>
      </Modal>


      </div>
    </AppLayout>
  </template>

  <script setup>
    import AppLayout from "./../Layout/App.vue";
      import{ ref } from 'vue';
      import { Link, useForm , router } from "@inertiajs/vue3";
      import FormInput from '@/Components/FormInput.vue';
      import FormPhoneInput from '@/Components/FormPhoneInput.vue';
      import FormSelect from '@/Components/FormSelect.vue';
      import BillingTable from '@/Components/Tables/BillingTable.vue';
      import Modal from '@/Components/Modal.vue';
      import UserTable from '@/Components/Tables/UserTable.vue';
      import { trans } from 'laravel-vue-i18n';


      

  const props = defineProps({
    title: String,
    organization: Array,
    plans: Array,
  })
  
  const firstOrganizationId = props.organization?.[0]?.uuid || null
  const isOpenFormModal = ref(false)
  const isLoading = ref(false);
  const selectedOrg = ref(null)

  const form = useForm({
    uuid: null,
    name: '',
  })

  const openEditModal = (org) => {
    selectedOrg.value = org
    form.uuid = org.uuid
    form.name = org.name
    isOpenFormModal.value = true
  }

  const closeModal = () => {
    selectedOrg.value = null
    isOpenFormModal.value = false
    form.reset()
  }


  const submitForm = async () => {

    if (form.uuid) {
     console.log('Submitting...');
       form.put(`/organization/${form.uuid}`, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      })
    }
  }

  const destroy = (uuid) => {
    if (confirm('هل أنت متأكد من حذف هذه المنظمة؟')) {
      router.delete(`/organization/${uuid}`, {
        preserveScroll: true,
      })
    }
  }

  const roleOptions = props.plans?.map(plan => ({
    value: plan.uuid,
    label: plan.name
  })) || []
  </script>
