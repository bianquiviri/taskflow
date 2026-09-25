<script setup>
import Icon from './Icon.vue';

defineProps({
    icon: { type: String, default: null },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
  <section class="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900/50">
    <span
      v-if="icon"
      class="mb-4 flex size-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"
    >
      <Icon
        :name="icon"
        class="size-6"
      />
    </span>
    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
      {{ title }}
    </h3>
    <p
      v-if="description"
      class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400"
    >
      {{ description }}
    </p>
    <div
      v-if="$slots.action"
      data-test="empty-state-action"
      class="mt-5"
    >
      <slot name="action" />
    </div>
  </section>
</template>
