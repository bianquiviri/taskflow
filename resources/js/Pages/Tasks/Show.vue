<script setup>
import { Head } from '@inertiajs/vue3';
import CommentForm from '../../Components/CommentForm.vue';
import CommentList from '../../Components/CommentList.vue';

defineProps({
  task: { type: Object, required: true },
  comments: { type: Array, required: true },
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

    <section class="mt-10">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
        Comments
      </h2>

      <div class="mt-4">
        <CommentList :comments="comments" />
      </div>

      <div class="mt-6">
        <CommentForm :url="`/tasks/${task.id}/comments`" />
      </div>
    </section>
  </div>
</template>
