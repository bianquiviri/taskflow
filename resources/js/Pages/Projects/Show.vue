<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    project: { type: Object, required: true },
});
</script>

<template>
  <Head :title="project.name" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
      {{ project.name }}
    </h1>
    <p class="mt-4 text-gray-600 dark:text-gray-300">
      {{ project.description ?? 'No description.' }}
    </p>
  </div>
</template>