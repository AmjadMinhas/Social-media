<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { MoreHorizontalIcon } from 'lucide-vue-next'
import { Handle, Position, useVueFlow, useNode } from '@vue-flow/core'

import { Menubar, MenubarMenu, MenubarTrigger, MenubarContent, MenubarItem } from '@modules/FlowBuilder/Pages/User/Components/ui/menubar'

import FormInput from '@/Components/FormInput.vue';
import FormSelect from '@/Components/FormSelect.vue';
import FlowMedia from '@modules/FlowBuilder/Pages/User/Components/vue-flow/FlowMedia.vue'
import type { NodeProps } from '@vue-flow/core'

const props = defineProps<NodeProps>()

const title = ref('Interactive Buttons')
const uuid = ref(props.data.uuid)

const fields = ref({
  type: 'interactive buttons',
  headerType: props.data.metadata?.fields?.headerType || 'none',
  headerText: props.data.metadata?.fields?.headerText || '',
  headerMedia: props.data.metadata?.fields?.headerMedia || [],
  body: props.data.metadata?.fields?.body || '',
  footer: props.data.metadata?.fields?.footer || '',
  buttonType: props.data.metadata?.fields?.buttonType || 'buttons',
  buttons: {
    button1: props.data.metadata?.fields?.buttons?.button1 || '',
    button2: props.data.metadata?.fields?.buttons?.button2 || '',
    button3: props.data.metadata?.fields?.buttons?.button3 || ''
  },
  ctaUrlButton: {
    displayText: props.data.metadata?.fields?.ctaUrlButton?.displayText || '',
    url: props.data.metadata?.fields?.ctaUrlButton?.url || '',
  }
})

const options = ref([
  { label: 'Reply Buttons', value: 'buttons' },
  { label: 'CTA URL Button', value: 'cta_url' },
]);

const options2 = ref([
  { value: 'none', label: 'None' },
  { value: 'text', label: 'Text' },
  { value: 'image', label: 'Image' },
  { value: 'video', label: 'Video' },
  { value: 'audio', label: 'Audio' },
  { value: 'document', label: 'Document' },
]);

const maxButtonLength = 20

const node = useNode()
const { removeNodes, nodes, addNodes, removeEdges, edges } = useVueFlow()
const textInput = ref(fields.value.body)
watch(textInput, (newValue) => {
  fields.value.body = newValue
  updateNodeMetadata()
})

watch(fields, () => {
  updateNodeMetadata()
}, { deep: true })

function updateNodeMetadata() {
  if (node && node.node && node.node.data && node.node.data.metadata) {
    node.node.data.metadata.fields = JSON.parse(JSON.stringify(fields.value))
  }
}

function removeEdgesForHandle(handleId: string) {
  const edgesToRemove = edges.value.filter(edge => edge.sourceHandle === handleId && edge.source === node.id)
  edgesToRemove.forEach(edge => removeEdges(edge.id))
}

watch(fields, (newFields) => {
  if (!newFields.buttons.button1) removeEdgesForHandle('a')
  if (!newFields.buttons.button2) removeEdgesForHandle('b')
  if (!newFields.buttons.button3) removeEdgesForHandle('c')

  if (newFields.buttonType === 'cta_url') {
    removeEdgesForHandle('a')
    removeEdgesForHandle('b')
    removeEdgesForHandle('c')
  } else {
    removeEdgesForHandle('d')
  }
}, { deep: true })

function handleClickDeleteBtn() {
  edges.value
    .filter(edge => edge.source === node.id || edge.target === node.id)
    .forEach(edge => removeEdges(edge.id))
  removeNodes(node.id)
}

function handleClickDuplicateBtn() {
  const { type, position, label, data } = node.node
  const newNode = {
    id: (nodes.value.length + 1).toString(),
    type,
    position: {
      x: position.x + 100,
      y: position.y + 100
    },
    label,
    data
  }
  addNodes(newNode)
}

const isEditTitle = ref(false)
const textAreaRef = ref<HTMLTextAreaElement | null>(null)

const addVariable = (variable: string) => {
  const textarea = textAreaRef.value;
  if (textarea && textInput.value.indexOf(variable) === -1) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;

    textInput.value = textInput.value.slice(0, start) + variable + textInput.value.slice(end);

    setTimeout(() => {
      textarea.setSelectionRange(start + variable.length, start + variable.length);
      textarea.focus();
    }, 0);
  }
}

const shouldShowWarning = computed(() => {
  const hasAtLeastOneButton =
    fields.value.buttons.button1 !== '' ||
    fields.value.buttons.button2 !== '' ||
    fields.value.buttons.button3 !== ''

  const hasCtaUrlFields =
    fields.value.ctaUrlButton.displayText !== '' &&
    fields.value.ctaUrlButton.url !== ''

  const isValidUrl = (url: string) => {
    const urlPattern = /^(https?:\/\/)([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?(\/\S*)?$/
    return urlPattern.test(url)
  }

  return (
    (fields.value.headerType !== '' && fields.value.headerType === 'text' && fields.value.headerText === '') ||
    (fields.value.headerType !== '' && fields.value.headerType !== 'text' && fields.value.headerType !== 'none' && fields.value.headerMedia.length === 0) ||
    fields.value.body === '' ||
    (fields.value.buttonType === 'buttons' && !hasAtLeastOneButton) ||
    (fields.value.buttonType === 'cta_url' && (!hasCtaUrlFields || !isValidUrl(fields.value.ctaUrlButton.url)))
  )
})


watch(() => fields.headerText, (val) => {
  if (val.length > 60) {
    fields.headerText = val.slice(0, 60)
  }
})


function getCharsCount(input: string): number {
  return input.length
}

function handlePaste(e: ClipboardEvent) {
  e.preventDefault()
  const pastedText = e.clipboardData?.getData('text') || ''
  const cleanedText = removeEmojis(pastedText).slice(0, 20)
  fields.value.buttonLabel = cleanedText
}

</script>

<template>
  <div class="rounded-sm border border-gray-200 bg-white p-3 shadow-md">
    <Handle type="target" :position="Position.Left" />
    <div class="flex flex-col gap-y-2">
      <div class="flex justify-between items-center">
        <div class="flex gap-x-2">
          <img src="~@/assets/images/icon_LLM.png" class="mt-1 h-4 w-4" alt="LLM icon" />
          <div class="flex flex-col gap-y-1">
            <FormInput v-if="isEditTitle" v-model="title" :type="'text'" @blur="() => (isEditTitle = false)" />
            <h3 class="text-base" v-else>{{ title }}</h3>
          </div>
        </div>

        <Menubar class="border-none">
          <menubar-menu>
            <menubar-trigger>
              <MoreHorizontalIcon />
            </menubar-trigger>
            <menubar-content>
              <menubar-item @click="handleClickDuplicateBtn"> Duplicate </menubar-item>
              <menubar-item @click="handleClickDeleteBtn"> Delete </menubar-item>
              <menubar-item @click="() => (isEditTitle = true)"> Rename </menubar-item>
            </menubar-content>
          </menubar-menu>
        </Menubar>
      </div>

      <div v-if="shouldShowWarning" class="flex items-center gap-x-2 bg-red-500 text-white rounded-md px-2 py-2">
        <span>
          <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none"><path d="m12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035q-.016-.005-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.017-.018m.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093q.019.005.029-.008l.004-.014l-.034-.614q-.005-.018-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01z"/><path fill="currentColor" d="m13.299 3.148l8.634 14.954a1.5 1.5 0 0 1-1.299 2.25H3.366a1.5 1.5 0 0 1-1.299-2.25l8.634-14.954c.577-1 2.02-1 2.598 0M12 15a1 1 0 1 0 0 2a1 1 0 0 0 0-2m0-7a1 1 0 0 0-.993.883L11 9v4a1 1 0 0 0 1.993.117L13 13V9a1 1 0 0 0-1-1"/></g></svg>
        </span>
        <span class="text-sm">Please fill all the required fields</span>
      </div>

      <span class="text-sm text-gray-500">Send interactive buttons or a call to action button to your recipients.</span>

      <div class="mb-4">
        <label class="text-sm mb-2">Header (Optional)</label>
        <FormSelect v-model="fields.headerType" :options="options2" :class="'col-span-4'" />
      </div>

      <div v-if="fields.headerType == 'text'" class="mb-4">
        <label class="text-sm mb-2"><span class="text-red-500">*</span> Header Text</label>
        <FormInput v-model="fields.headerText"  value="fields.headerText" :placeholder="'Enter header text'" :type="'text'" :class="'col-span-4'" :maxlength="60"/>
        <span class="text-xs text-gray-500">{{ getCharsCount(fields.headerText) }}/60</span>
      </div>

      <FlowMedia
        v-if="fields.headerType !== 'text' && fields.headerType !== 'none'"
        v-model="fields.headerMedia"
        :type="fields.headerType"
        :uuid="uuid"
        :nodeId="node.id"
      />

      <div class="mb-4">
        <label class="text-sm mb-2"><span class="text-red-500">*</span> Body</label>
        <textarea
          ref="textAreaRef"
          class="block w-full rounded-md border-0 py-1.5 px-4 text-gray-900 shadow-sm outline-none ring-1 ring-inset placeholder:text-gray-400 sm:text-sm sm:leading-6 ring-gray-300"
          v-model="textInput"
          rows="5"
          placeholder="Enter text"
        />
      </div>

      <Menubar class="border-none">
        <menubar-menu>
          <menubar-trigger>
            <button type="button" class="hover:bg-slate-100 bg-slate-100 rounded p-1 text-sm">Add variable</button>
          </menubar-trigger>
          <menubar-content>
            <menubar-item @click="addVariable('{first_name}')"> {first_name} </menubar-item>
            <menubar-item @click="addVariable('{last_name}')"> {last_name} </menubar-item>
            <menubar-item @click="addVariable('{full_name}')"> {full_name} </menubar-item>
            <menubar-item @click="addVariable('{phone_number}')"> {phone_number} </menubar-item>
            <menubar-item @click="addVariable('{email}')"> {email} </menubar-item>
            <menubar-item @click="addVariable('{city}')"> {city} </menubar-item>
            <menubar-item @click="addVariable('{country}')"> {country} </menubar-item>
          </menubar-content>
        </menubar-menu>
      </Menubar>

      <div class="mb-4">
        <label class="text-sm mb-2">Footer Text (Optional)</label>
        <FormInput v-model="fields.footer" :placeholder="'Enter footer text'" :type="'text'" :class="'col-span-4'" />
      </div>

      <div class="mb-4">
        <label class="text-sm mb-2">Button type:</label>

        <div class="flex space-x-4">
          <label
            v-for="(option, index) in options"
            :key="index"
            class="inline-flex items-center cursor-pointer text-sm"
          >
            <input
              type="radio"
              :value="option.value"
              v-model="fields.buttonType"
              class="form-radio text-blue-500 focus:ring-0 transition duration-150 ease-in-out"
            />
            <span class="ml-2 text-gray-700">{{ option.label }}</span>
          </label>
        </div>
      </div>

      <div v-if="fields.buttonType === 'buttons'" class="border rounded p-3">
        <label class="mb-2"><span class="text-red-500">*</span> Reply Buttons (at least 1 button)</label>

        <div class="mb-4 relative">
          <label class="text-sm mb-2">Button 1 Label</label>
          <FormInput v-model="fields.buttons.button1" :maxLength="maxButtonLength" :type="'text'" :class="'col-span-4'" @paste="(e) => handlePaste(e)"/>
          <span class="text-xs text-gray-500">{{ getCharsCount(fields.buttons.button1) }} / {{ maxButtonLength }}</span>
          <Handle v-if="fields.buttons.button1" id="a" type="source" :position="Position.Right" style="right: -25px;" />
        </div>

        <div class="mb-4 relative">
          <label class="text-sm mb-2">Button 2 Label</label>
          <FormInput v-model="fields.buttons.button2" :maxLength="maxButtonLength" :type="'text'" :class="'col-span-4'" @paste="(e) => handlePaste(e)"/>
          <span class="text-xs text-gray-500">{{ getCharsCount(fields.buttons.button2) }} / {{ maxButtonLength }}</span>
          <Handle v-if="fields.buttons.button2" id="b" type="source" :position="Position.Right" style="right: -25px;" />
        </div>

        <div class="mb-2 relative">
          <label class="text-sm mb-2">Button 3 Label</label>
          <FormInput v-model="fields.buttons.button3" :maxLength="maxButtonLength" :type="'text'" :class="'col-span-4'" @paste="(e) => handlePaste(e)"/>
          <span class="text-xs text-gray-500">{{ getCharsCount(fields.buttons.button3) }} / {{ maxButtonLength }}</span>
          <Handle v-if="fields.buttons.button3" id="c" type="source" :position="Position.Right" style="right: -25px;" />
        </div>
      </div>

      <div v-if="fields.buttonType === 'cta_url'" class="border rounded p-3">
        <label class="text-sm mb-2">CTA Button</label>
        <div class="mb-4">
          <label class="text-sm mb-2">Display text</label>
          <FormInput v-model="fields.ctaUrlButton.displayText" :type="'text'" :class="'col-span-4'" @paste="(e) => handlePaste(e)" />
          <span class="text-xs text-gray-500">{{ getCharsCount(fields.ctaUrlButton.displayText) }} / {{ maxButtonLength }}</span>
        </div>

        <div class="mb-4">
          <label class="text-sm mb-2">Button URL</label>
          <FormInput v-model="fields.ctaUrlButton.url" :type="'url'" :class="'col-span-4'" />
        </div>

        <Handle id="d" type="source" :position="Position.Right" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.form-radio:checked {
  background-color: #3b82f6;
  border-color: #3b82f6;
}
</style>
