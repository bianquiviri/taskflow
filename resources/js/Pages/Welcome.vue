<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: { type: String, required: true },
    version: { type: String, required: true },
});
</script>

<template>
  <Head title="Home" />

  <div class="flex min-h-dvh flex-col items-center justify-center px-6">
    <main class="mx-auto w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
        {{ appName }}
      </h1>
      <p class="mt-4 text-lg leading-relaxed text-gray-600 dark:text-gray-300">
        Professional project management platform built with Laravel 13, Inertia and Vue 3.
      </p>

      <div class="mt-8 rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
        <p><span class="font-semibold text-gray-700 dark:text-gray-200">Framework:</span> Laravel</p>
        <p><span class="font-semibold text-gray-700 dark:text-gray-200">Version:</span> {{ version }}</p>
      </div>
    </main>
  </div>
</template>