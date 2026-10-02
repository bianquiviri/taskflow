<script setup>
import { computed, toRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageSkeleton from '../../Components/PageSkeleton.vue';
import TaskFilterForm from '../../Components/TaskFilterForm.vue';
import TaskList from '../../Components/TaskList.vue';
import { useActiveFilters } from '../../Composables/useActiveFilters';
import { usePageLoading } from '../../Composables/usePageLoading';

const props = defineProps({
    tasks: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
});

const { loading } = usePageLoading();

const hasFilters = useActiveFilters(toRef(props, 'filters'));

const emptyTitle = computed(() => (hasFilters.value
    ? 'No task matches the filters'
    : 'Nothing is assigned to you'));

const emptyDescription = computed(() => (hasFilters.value
    ? 'Clear the filters to see every task assigned to you.'
    : 'Tasks assigned to you in any project you belong to show up here.'));
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

    <PageSkeleton
      v-if="loading"
      :rows="5"
    />

    <TaskList
      v-else
      :tasks="tasks"
      show-project
      :empty-title="emptyTitle"
      :empty-description="emptyDescription"
    >
      <template
        v-if="!hasFilters"
        #action
      >
        <Link
          data-test="task-list-cta"
          href="/projects"
          class="inline-flex min-h-10 items-center rounded-control bg-brand-600 px-4 py-2 text-sm font-semibold text-content-inverted"
        >
          Go to projects
        </Link>
      </template>
    </TaskList>
  </div>
</template>
