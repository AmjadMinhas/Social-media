<script setup lang="ts">
import { markRaw, nextTick, ref, watch, onMounted } from 'vue'
import axios from "axios"
import { ConnectionMode, VueFlow, useVueFlow } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'

import StartNode from '@modules/FlowBuilder/Pages/User/Components/vue-flow/nodes/start-node.vue'
import ButtonsNode from '@modules/FlowBuilder/Pages/User/Components/vue-flow/nodes/buttons-node.vue'
import ListNode from '@modules/FlowBuilder/Pages/User/Components/vue-flow/nodes/list-node.vue'
import MediaNode from '@modules/FlowBuilder/Pages/User/Components/vue-flow/nodes/Media-node.vue'
import TextNode from '@modules/FlowBuilder/Pages/User/Components/vue-flow/nodes/Text-node.vue'
import { Test_data } from '@/lib/constant'

import type { Dimensions, Elements } from '@vue-flow/core'

const props = defineProps(['uuid', 'flow']);

const elements = ref<Elements>()

const nodeTypes = {
  start: markRaw(StartNode),
  list: markRaw(ListNode),
  buttons: markRaw(ButtonsNode),
  media: markRaw(MediaNode),
  text: markRaw(TextNode),
}

const { findNode, nodes, edges, viewport, addNodes, addEdges, project, vueFlowRef, onConnect, onNodeDragStop, setNodes, setEdges, setViewport, onViewportChange } =
  useVueFlow();


onMounted(() => {
  if (props.flow.metadata) {
    const savedData = JSON.parse(props.flow.metadata);
    setNodes(savedData.nodes || []);
    setEdges(savedData.edges || []);
    setViewport(savedData.viewport || []);
  } else {
    setNodes([
      {
        id: '1',
        type: 'start',
        label: 'start',
        position: { x: 25, y: 10 },
        data: {
          uuid: props.uuid,
          title: 'start',
          metadata: {
            fields: [],
          },
        }
      },
    ]);
  }
});

// Watch for changes in all nodes to save data
watch(
  [nodes, edges],
  () => {
    saveNodesAndEdges();
  },
  { deep: true }
);

onConnect((params) => {
  addEdges(params)
})

onViewportChange(() => {
  saveNodesAndEdges();
})

const emit = defineEmits(['updateStatus', 'updatePayload']);

const saveNodesAndEdges = () => {
  emit('updatePayload', JSON.stringify({ nodes: nodes.value, edges: edges.value, viewport: viewport.value }));
};

function handleOnDrop(event: DragEvent) {
  const type = event.dataTransfer?.getData('application/vueflow')
  if (type === 'workflow') {
    const { nodes, edges, position, zoom } = Test_data
    const [x = 0, y = 0] = position
    setNodes(nodes)
    setEdges(edges)
    setViewport({ x, y, zoom: zoom || 0 })
    return
  }

  const { left, top } = vueFlowRef.value!.getBoundingClientRect()

  const position = project({
    x: event.clientX - left,
    y: event.clientY - top
  })

  //console.log(nodes.value.length);
  //console.log(nodes.value)

  const lastNodeId = nodes.value.length 
  ? Math.max(...nodes.value.map(node => parseInt(node.id, 10))) + 1 
  : 1;

  const newNode = {
    id: (lastNodeId + 1).toString(),
    type,
    position,
    label: `${type} node`,
    data: {
      uuid: props.uuid,
      title: type,
      metadata: {
        fields: [],
      },
    }
  }

  addNodes([newNode])

  nextTick(() => {
    const node = findNode(newNode.id)
    const stop = watch(
      () => node!.dimensions,
      (dimensions: Dimensions) => {
        if (dimensions.width > 0 && dimensions.height > 0 && node) {
          node.position = {
            x: node.position.x - node.dimensions.width / 2,
            y: node.position.y - node.dimensions.height / 2
          }
          stop()
        }
      },
      { deep: true, flush: 'post' }
    )
  })
}
function handleOnDragOver(event: DragEvent) {
  event.preventDefault()

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}
</script>
<template>
  <div class="relative h-full w-full" id="main-canvas" @drop="handleOnDrop" @dragover="handleOnDragOver">
    <VueFlow v-model="elements" :node-types="nodeTypes" :connection-mode="ConnectionMode.Strict">
      <Controls />
      <Background />
    </VueFlow>
  </div>
  <!--<div class="h-1/2">
    {{ nodes }}
  </div>-->
  
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';

#main-canvas {
  --vf-handle: hsl(var(--primary));
}

.vue-flow__handle {
  width: 18px;
  height: 18px;
}
</style>
