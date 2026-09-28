<script setup>
import { computed, ref, toRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useCan } from '../../Composables/useCan';
import { useTaskStatusChange } from '../../Composables/useTaskStatusChange';
import Button from '../../Components/Button.vue';
import CommentForm from '../../Components/CommentForm.vue';
import CommentList from '../../Components/CommentList.vue';
import FormSelect from '../../Components/FormSelect.vue';
import Icon from '../../Components/Icon.vue';
import StatusPill from '../../Components/StatusPill.vue';
import TaskFormModal from '../../Components/TaskFormModal.vue';

const props = defineProps({
    task: { type: Object, required: true },
    comments: { type: Array, required: true },
    priorities: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
});

const { can } = useCan(toRef(props, 'permissions'));
const { changeStatus, error } = useTaskStatusChange({
    canChange: () => can('tasks.changeStatus'),
    apply: (pageProps, task, status) => ({ task: { ...pageProps.task, status } }),
});

const formOpen = ref(false);

const reachable = computed(() => {
    const allowed = props.statuses.find((status) => status.value === props.task.status)?.allows ?? [];

    return props.statuses.filter((status) => allowed.includes(status.value));
});
</script>

<template>
  <Head :title="task.title" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <p class="text-sm text-content-subtle">
      {{ task.project.name }}
    </p>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
      <h1 class="text-3xl font-bold tracking-tight text-content">
        {{ task.title }}
      </h1>

      <Button
        v-if="can('tasks.update')"
        data-test="task-edit"
        variant="secondary"
        @click="formOpen = true"
      >
        Edit task
      </Button>
    </div>

    <p class="mt-4 text-content-muted">
      {{ task.description ?? 'No description.' }}
    </p>

    <p
      v-if="error"
      data-test="status-error"
      class="mt-4 flex items-center gap-2 rounded-panel border border-danger/40 bg-danger-soft px-3 py-2 text-sm text-danger-text"
      role="alert"
    >
      <Icon
        name="warning"
        class="size-4"
      />
      {{ error }}
    </p>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
      <div>
        <dt class="text-sm font-medium text-content-subtle">
          Status
        </dt>
        <dd class="mt-1">
          <FormSelect
            v-if="can('tasks.changeStatus')"
            :model-value="task.status"
            data-test="task-status"
            name="status"
            :aria-label="`Status of ${task.title}`"
            @update:model-value="changeStatus(task, $event)"
          >
            <option
              v-for="status in reachable"
              :key="status.value"
              :value="status.value"
            >
              {{ status.label }}
            </option>
          </FormSelect>

          <StatusPill
            v-else
            data-test="task-status-pill"
            :value="task.status"
          />
        </dd>
      </div>

      <div>
        <dt class="text-sm font-medium text-content-subtle">
          Priority
        </dt>
        <dd
          data-test="task-priority"
          class="mt-1"
        >
          <StatusPill :value="task.priority" />
        </dd>
      </div>

      <div>
        <dt class="text-sm font-medium text-content-subtle">
          Assignee
        </dt>
        <dd class="mt-1 text-content">
          {{ task.assignee?.name ?? 'Unassigned' }}
        </dd>
      </div>
    </dl>

    <section class="mt-10">
      <h2 class="text-lg font-semibold text-content">
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

  <TaskFormModal
    v-model="formOpen"
    :task="task"
    :priorities="priorities"
  />
</template>
