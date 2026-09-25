<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
  project: { type: Object, required: true },
  tasks: { type: Array, required: true },
  filters: { type: Object, required: true },
});
</script>

<template>
  <Head :title="`${project.name} tasks`" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
      {{ project.name }} Tasks
    </h1>

    <p
      v-if="tasks.length === 0"
      class="mt-6 text-gray-600 dark:text-gray-300"
    >
      No tasks match these filters.
    </p>

    <ul class="mt-6 space-y-4">
      <li
        v-for="task in tasks"
        :key="task.id"
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
      >
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ task.title }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
          {{ task.status }} · {{ task.priority }}
        </p>
      </li>
    </ul>
  </div>
</template>
