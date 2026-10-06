<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import EmptyState from './EmptyState.vue';
import StatusPill from './StatusPill.vue';
import TaskPagination from './TaskPagination.vue';

const props = defineProps({
    tasks: { type: Object, required: true },
    showProject: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'No tasks found' },
    emptyDescription: { type: String, default: 'No task matches the current filters.' },
});

const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });

const rows = computed(() => props.tasks?.data ?? []);
const total = computed(() => props.tasks?.total ?? 0);

function formatDate(value) {
    return dateFormatter.format(new Date(value));
}
</script>

<template>
  <div class="mt-6">
    <p
      v-if="total > 0"
      data-test="task-count"
      class="text-sm text-content-subtle"
    >
      {{ total }} {{ total === 1 ? 'task' : 'tasks' }}
    </p>

    <div
      v-if="rows.length === 0"
      data-test="task-list-empty"
      class="mt-4"
    >
      <EmptyState
        icon="info"
        :title="emptyTitle"
        :description="emptyDescription"
      >
        <template
          v-if="$slots.action"
          #action
        >
          <slot name="action" />
        </template>
      </EmptyState>
    </div>

    <ul
      v-else
      data-test="task-list"
      class="mt-4 space-y-3"
    >
      <li
        v-for="task in rows"
        :key="task.id"
        data-test="task-row"
        class="rounded-panel border border-line bg-raised p-4"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <Link
              :href="`/tasks/${task.id}`"
              class="font-semibold text-content hover:text-brand-text"
            >
              {{ task.title }}
            </Link>
            <p
              v-if="showProject && task.project"
              data-test="task-row-project"
              class="mt-0.5 text-xs text-content-subtle"
            >
              {{ task.project.name }}
            </p>
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <StatusPill :value="task.status" />
            <StatusPill :value="task.priority" />
          </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-content-subtle">
          <p data-test="task-row-assignee">
            Assignee: {{ task.assignee?.name ?? 'Unassigned' }}
          </p>
          <p
            v-if="task.due_date"
            data-test="task-row-due"
          >
            Due: {{ formatDate(task.due_date) }}
          </p>
        </div>
      </li>
    </ul>

    <TaskPagination :links="tasks.links ?? []" />
  </div>
</template>
