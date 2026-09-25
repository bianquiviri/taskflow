<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
  task: { type: Object, required: true },
});
</script>

<template>
  <Head :title="task.title" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <p class="text-sm text-gray-500 dark:text-gray-400">
      {{ task.project.name }}
    </p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
      {{ task.title }}
    </h1>
    <p class="mt-4 text-gray-600 dark:text-gray-300">
      {{ task.description ?? 'No description.' }}
    </p>
    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
      <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Status
        </dt>
        <dd class="mt-1 text-gray-900 dark:text-white">
          {{ task.status }}
        </dd>
      </div>
      <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Priority
        </dt>
        <dd class="mt-1 text-gray-900 dark:text-white">
          {{ task.priority }}
        </dd>
      </div>
      <div>
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Assignee
        </dt>
        <dd class="mt-1 text-gray-900 dark:text-white">
          {{ task.assignee?.name ?? 'Unassigned' }}
        </dd>
      </div>
    </dl>
  </div>
</template>
