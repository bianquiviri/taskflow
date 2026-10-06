<script setup>
import Skeleton from './Skeleton.vue';

defineProps({
    rows: { type: Number, default: 3 },
});
</script>

<template>
  <div
    data-test="page-skeleton"
    role="status"
    aria-busy="true"
    class="mt-6 space-y-3"
  >
    <span class="sr-only">Loading…</span>

    <div
      v-for="row in rows"
      :key="row"
      data-test="page-skeleton-row"
      class="rounded-panel border border-line bg-raised p-4"
    >
      <Skeleton class="h-4 w-1/3" />
      <Skeleton class="mt-3 h-3 w-2/3" />
    </div>
  </div>
</template>