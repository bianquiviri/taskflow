<script setup>
import { computed, ref } from 'vue';
import EmptyState from './EmptyState.vue';
import TaskCard from './TaskCard.vue';

const props = defineProps({
    tasks: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
    canChange: { type: Function, default: () => false },
    isPending: { type: Function, default: () => false },
    emptyTitle: { type: String, default: 'No tasks on the board' },
    emptyDescription: { type: String, default: 'Create a task, or clear the filters to see more.' },
});

const emit = defineEmits(['move']);

const dragged = ref(null);
const over = ref(null);

const rows = computed(() => props.tasks?.data ?? []);
const columns = computed(() =>
    props.statuses.map((status) => ({
        ...status,
        tasks: rows.value.filter((task) => task.status === status.value),
    })),
);

/**
 * A column accepts the dragged task only when the domain allows the move, which
 * the server would otherwise refuse. With nothing dragged every column is open.
 */
function canDrop(status, task = dragged.value) {
    if (!task) {
        return true;
    }

    return allowedTargets(task.status).includes(status);
}

function allowedTargets(from) {
    return props.statuses.find((column) => column.value === from)?.allows ?? [];
}

function drop(status) {
    const task = dragged.value;
    const allowed = canDrop(status, task);

    dragged.value = null;
    over.value = null;

    if (task && allowed && task.status !== status) {
        emit('move', task, status);
    }
}

function leave(status) {
    if (over.value === status) {
        over.value = null;
    }
}

function request(task, status) {
    emit('move', task, status);
}
</script>

<template>
  <div
    v-if="rows.length === 0"
    data-test="board-empty"
    class="mt-6"
  >
    <EmptyState
      icon="info"
      :title="emptyTitle"
      :description="emptyDescription"
    />
  </div>

  <div
    v-else
    data-test="board"
    class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5"
  >
    <section
      v-for="column in columns"
      :key="column.value"
      data-test="board-column"
      :data-column="column.value"
      class="rounded-panel border border-line bg-sunken/60 p-3"
    >
      <header class="flex items-center justify-between gap-2 px-1">
        <h3
          data-test="board-column-label"
          class="text-sm font-semibold text-content"
        >
          {{ column.label }}
        </h3>
        <span
          data-test="board-column-count"
          class="rounded-full bg-sunken px-2 py-0.5 text-xs text-content-subtle"
        >
          {{ column.tasks.length }}
        </span>
      </header>

      <div
        data-test="board-dropzone"
        :data-allowed="canDrop(column.value)"
        :data-over="over === column.value"
        class="mt-2 min-h-24 space-y-2 rounded-panel border-2 border-dashed p-2 transition-colors"
        :class="[
          canDrop(column.value) ? 'border-line' : 'border-line opacity-50',
          over === column.value && canDrop(column.value) ? 'border-brand-400 bg-brand-soft' : '',
        ]"
        @dragover.prevent="over = column.value"
        @dragleave="leave(column.value)"
        @drop.prevent="drop(column.value)"
      >
        <TaskCard
          v-for="task in column.tasks"
          :key="task.id"
          :task="task"
          :statuses="statuses"
          :can-change="canChange"
          :is-pending="isPending"
          @grab="dragged = $event"
          @change="request"
        />

        <p
          v-if="column.tasks.length === 0"
          class="px-1 py-4 text-center text-xs text-content-subtle"
        >
          Drop a task here
        </p>
      </div>
    </section>
  </div>
</template>
