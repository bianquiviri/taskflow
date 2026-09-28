<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import StatusPill from './StatusPill.vue';

const props = defineProps({
    task: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    canChange: { type: Function, default: () => false },
    isPending: { type: Function, default: () => false },
});

const emit = defineEmits(['grab', 'change']);

const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });

const allowedTargets = computed(
    () => props.statuses.find((status) => status.value === props.task.status)?.allows ?? [],
);

const reachable = computed(() =>
    props.statuses.filter((status) => allowedTargets.value.includes(status.value)),
);

const busy = computed(() => props.isPending(props.task.id));

function startDrag() {
    emit('grab', props.task);
}

function select(event) {
    emit('change', props.task, event.target.value);
}

function formatDate(value) {
    return dateFormatter.format(new Date(value));
}
</script>

<template>
  <article
    data-test="task-card"
    :draggable="canChange(task)"
    :aria-busy="busy"
    class="rounded-panel border border-line bg-raised p-3 shadow-sm"
    :class="[canChange(task) ? 'cursor-grab hover:border-line-strong' : '', busy ? 'opacity-60' : '']"
    @dragstart="startDrag"
  >
    <div class="flex items-start justify-between gap-2">
      <Link
        :href="`/tasks/${task.id}`"
        class="text-sm font-semibold text-content hover:text-brand-text"
      >
        {{ task.title }}
      </Link>

      <span
        v-if="busy"
        data-test="task-card-pending"
        class="text-xs text-content-subtle"
      >
        Saving…
      </span>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-2">
      <span data-test="task-card-priority">
        <StatusPill :value="task.priority" />
      </span>
      <span
        v-if="task.due_date"
        data-test="task-card-due"
        class="text-xs text-content-subtle"
      >
        {{ formatDate(task.due_date) }}
      </span>
    </div>

    <p
      data-test="task-card-assignee"
      class="mt-2 truncate text-xs text-content-subtle"
    >
      {{ task.assignee?.name ?? 'Unassigned' }}
    </p>

    <p
      v-if="canChange(task)"
      class="mt-3 border-t border-line pt-2 text-xs text-content-subtle"
    >
      <span class="sr-only">Status of {{ task.title }}</span>
      <select
        data-test="task-card-status"
        :value="task.status"
        :disabled="busy"
        :aria-label="`Status of ${task.title}`"
        class="w-full rounded-control border border-line-strong bg-raised px-2 py-1 text-xs text-content focus:outline-none focus:ring-2 focus:ring-focus"
        @change="select"
      >
        <option
          v-for="status in reachable"
          :key="status.value"
          :value="status.value"
        >
          {{ status.label }}
        </option>
      </select>
    </p>
  </article>
</template>
