<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
});
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-gray-100 px-4 py-10 sm:px-6 lg:bg-gray-950">
    <main class="w-full max-w-md">
      <Link
        href="/"
        class="mx-auto flex w-fit items-center gap-3 text-gray-900 dark:text-white"
        aria-label="TaskFlow home"
      >
        <span class="flex size-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
          <Icon
            name="logo"
            class="size-7"
          />
        </span>
        <span class="text-2xl font-bold tracking-tight">{{ appName }}</span>
      </Link>

      <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 dark:border-gray-800 dark:bg-gray-900">
        <slot />
      </div>

      <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
        Project management, organized.
      </p>
    </main>
  </div>
</template>
