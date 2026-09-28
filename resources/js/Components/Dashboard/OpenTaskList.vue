<script setup>
import { Link } from '@inertiajs/vue3';
import Badge from '../Badge.vue';
import EmptyState from '../EmptyState.vue';
import StatusPill from '../StatusPill.vue';

defineProps({
    tasks: { type: Array, required: true },
});

const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });

function formatDate(value) {
    return dateFormatter.format(new Date(value));
}
</script>

<template>
  <section
    data-test="open-tasks"
    class="rounded-panel border border-line bg-raised p-5 shadow-sm"
  >
    <header class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-semibold text-content">
        Needs attention
      </h2>
      <Link
        data-test="open-tasks-all"
        href="/tasks/mine"
        class="text-xs font-medium text-brand-text hover:underline"
      >
        My tasks
      </Link>
    </header>

    <EmptyState
      v-if="tasks.length === 0"
      class="mt-4"
      icon="check"
      title="Nothing open"
      description="Every task in your projects is done or cancelled."
    />

    <ul
      v-else
      class="mt-4 divide-y divide-line"
    >
      <li
        v-for="task in tasks"
        :key="task.id"
        data-test="open-task"
        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 py-3 first:pt-0 last:pb-0"
      >
        <div class="min-w-0">
          <Link
            data-test="open-task-link"
            :href="`/tasks/${task.id}`"
            class="font-medium text-content hover:text-brand-text"
          >
            {{ task.title }}
          </Link>
          <p
            data-test="open-task-project"
            class="text-xs text-content-subtle"
          >
            {{ task.project?.name }}
          </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <StatusPill :value="task.status" />
          <StatusPill :value="task.priority" />
          <Badge
            v-if="task.overdue"
            data-test="open-task-overdue"
            tone="red"
          >
            Overdue
          </Badge>
          <span
            v-else-if="task.due_date"
            data-test="open-task-due"
            class="text-xs text-content-subtle"
          >
            Due {{ formatDate(task.due_date) }}
          </span>
        </div>
      </li>
    </ul>
  </section>
</template>
