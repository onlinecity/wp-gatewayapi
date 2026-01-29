<script setup lang="ts">
defineProps<{
  icon?: string
}>()
</script>
<template>
  <div class="prose max-w-none mb-8 flex w-full items-center">
    <h1 class="font-title text-2xl md:text-3xl lg:text-4xl font-bold flex-1 flex gap-4">
      <Icon :icon="icon" v-if="icon" />
      <slot/>
    </h1>
    <div><slot name="actions" /></div>
  </div>
</template>