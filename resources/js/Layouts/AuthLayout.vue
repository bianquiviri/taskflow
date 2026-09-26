<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
});
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-sunken px-4 py-10 sm:px-6 lg:bg-canvas">
    <main class="w-full max-w-md">
      <Link
        href="/"
        class="mx-auto flex w-fit items-center gap-3 text-content"
        aria-label="TaskFlow home"
      >
        <span class="flex size-11 items-center justify-center rounded-shell bg-brand-600 text-content-inverted shadow-sm">
          <Icon
            name="logo"
            class="size-7"
          />
        </span>
        <span class="text-2xl font-bold tracking-tight">{{ appName }}</span>
      </Link>

      <div class="mt-8 rounded-shell border border-line bg-raised p-6 shadow-sm sm:p-8">
        <slot />
      </div>

      <p class="mt-6 text-center text-xs text-content-subtle">
        Project management, organized.
      </p>
    </main>
  </div>
</template>
