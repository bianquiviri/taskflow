<script setup>
import { Head, Link } from '@inertiajs/vue3';
import TaskFilterForm from '../../Components/TaskFilterForm.vue';
import TaskList from '../../Components/TaskList.vue';

defineProps({
    project: { type: Object, required: true },
    tasks: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
});
</script>

<template>
  <Head :title="`${project.name} tasks`" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-3xl font-bold tracking-tight text-content">
        {{ project.name }} Tasks
      </h1>

      <Link
        href="/tasks/mine"
        class="text-sm font-medium text-brand-text hover:underline"
      >
        My tasks
      </Link>
    </div>

    <div class="mt-6">
      <TaskFilterForm
        :url="`/projects/${project.id}/tasks`"
        :filters="filters"
        :statuses="filterOptions.statuses"
        :priorities="filterOptions.priorities"
        :assignees="filterOptions.assignees"
      />
    </div>

    <TaskList :tasks="tasks" />
  </div>
</template>
