<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  current: number;
  pages: number;
}>();

const emit = defineEmits<{
  (e: 'update:page', page: number): void;
}>();

const setPage = (page: number) => {
  if (page >= 1 && page <= props.pages && page !== props.current) {
    emit('update:page', page);
  }
};

const items = computed(() => {
  const { current, pages } = props;
  const delta = 4;
  const range = [];
  const rangeWithDots: (number | string)[] = [];
  let l: number | undefined;

  for (let i = 1; i <= pages; i++) {
    if (i === 1 || i === pages || (i >= current - delta && i <= current + delta)) {
      range.push(i);
    }
  }

  for (const i of range) {
    if (l !== undefined) {
      if (i - l === 2) {
        rangeWithDots.push(l + 1);
      } else if (i - l !== 1) {
        rangeWithDots.push('...');
      }
    }
    rangeWithDots.push(i);
    l = i;
  }

  return rangeWithDots;
});
</script>

<template>
  <div v-if="pages > 1" class="flex justify-center p-4">
    <div class="join">
      <template v-for="(item, index) in items" :key="index">
        <button 
          v-if="item !== '...'"
          class="join-item btn" 
          :class="{ 'btn-primary': item === current }"
          @click="setPage(item as number)"
        >
          {{ item }}
        </button>
        <button v-else class="join-item btn btn-disabled">...</button>
      </template>
    </div>
  </div>
</template>
