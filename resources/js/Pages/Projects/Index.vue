<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    projects: { type: Array, required: true },
});
</script>

<template>
  <Head title="Projects" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
      Projects
    </h1>

    <p
      v-if="projects.length === 0"
      class="mt-6 text-gray-600 dark:text-gray-300"
    >
      No active projects yet.
    </p>

    <ul class="mt-6 space-y-4">
      <li
        v-for="project in projects"
        :key="project.id"
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
      >
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ project.name }}
        </h2>
        <p
          v-if="project.description"
          class="mt-1 text-sm text-gray-600 dark:text-gray-300"
        >
          {{ project.description }}
        </p>
      </li>
    </ul>
  </div>
</template>