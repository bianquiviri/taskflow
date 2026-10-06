<script setup>
import Icon from './Icon.vue';

defineProps({
    icon: { type: String, default: null },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
  <section class="flex min-h-64 flex-col items-center justify-center rounded-panel border border-dashed border-line-strong bg-canvas px-6 py-12 text-center">
    <span
      v-if="icon"
      class="mb-4 flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand-text"
    >
      <Icon
        :name="icon"
        class="size-6"
      />
    </span>
    <h3 class="text-base font-semibold text-content">
      {{ title }}
    </h3>
    <p
      v-if="description"
      class="mt-1 max-w-md text-sm text-content-subtle"
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
