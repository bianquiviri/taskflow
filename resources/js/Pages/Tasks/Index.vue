<script setup>
import { computed, toRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageSkeleton from '../../Components/PageSkeleton.vue';
import TaskFilterForm from '../../Components/TaskFilterForm.vue';
import TaskList from '../../Components/TaskList.vue';
import { useActiveFilters } from '../../Composables/useActiveFilters';
import { usePageLoading } from '../../Composables/usePageLoading';

const props = defineProps({
    project: { type: Object, required: true },
    tasks: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
});

const { loading } = usePageLoading();

const hasFilters = useActiveFilters(toRef(props, 'filters'));

const emptyTitle = computed(() => (hasFilters.value
    ? 'No task matches the filters'
    : `No tasks in ${props.project.name} yet`));

const emptyDescription = computed(() => (hasFilters.value
    ? 'Clear the filters to see every task of the project.'
    : 'The board of the project is where the tasks are created.'));
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

    <PageSkeleton
      v-if="loading"
      :rows="5"
    />

    <TaskList
      v-else
      :tasks="tasks"
      :empty-title="emptyTitle"
      :empty-description="emptyDescription"
    >
      <template
        v-if="!hasFilters"
        #action
      >
        <Link
          data-test="task-list-cta"
          :href="`/projects/${project.id}`"
          class="inline-flex min-h-10 items-center rounded-control bg-brand-600 px-4 py-2 text-sm font-semibold text-content-inverted"
        >
          Open the board
        </Link>
      </template>
    </TaskList>
  </div>
</template>
