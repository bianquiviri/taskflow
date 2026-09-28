<script setup>
import { Head } from '@inertiajs/vue3';
import TaskFilterForm from '../../Components/TaskFilterForm.vue';
import TaskList from '../../Components/TaskList.vue';

defineProps({
    tasks: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
});
</script>

<template>
  <Head title="My tasks" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <h1 class="text-3xl font-bold tracking-tight text-content">
      My Tasks
    </h1>
    <p class="mt-2 text-sm text-content-subtle">
      Every task assigned to you across your projects, soonest due date first.
    </p>

    <div class="mt-6">
      <TaskFilterForm
        url="/tasks/mine"
        :filters="filters"
        :statuses="filterOptions.statuses"
        :priorities="filterOptions.priorities"
      />
    </div>

    <TaskList
      :tasks="tasks"
      show-project
    />
  </div>
</template>
