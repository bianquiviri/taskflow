<script setup>
import { computed, ref, toRef } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useActiveFilters } from '../../Composables/useActiveFilters';
import { useCan } from '../../Composables/useCan';
import { usePageLoading } from '../../Composables/usePageLoading';
import { useTaskStatusChange } from '../../Composables/useTaskStatusChange';
import Avatar from '../../Components/Avatar.vue';
import Badge from '../../Components/Badge.vue';
import Button from '../../Components/Button.vue';
import Icon from '../../Components/Icon.vue';
import ActivityTimeline from '../../Components/Project/ActivityTimeline.vue';
import Skeleton from '../../Components/Skeleton.vue';
import TaskBoard from '../../Components/TaskBoard.vue';
import TaskFilterForm from '../../Components/TaskFilterForm.vue';
import TaskFormModal from '../../Components/TaskFormModal.vue';
import TaskList from '../../Components/TaskList.vue';
import TaskPagination from '../../Components/TaskPagination.vue';

const props = defineProps({
    project: { type: Object, required: true },
    view: { type: String, default: 'board' },
    tasks: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    progress: { type: Object, required: true },
    permissions: { type: Array, default: () => [] },
    activity: { type: Object, required: true },
});

const { can } = useCan(toRef(props, 'permissions'));
const { changeStatus, error, isPending } = useTaskStatusChange({
    canChange: (task) => can(`tasks.${task.id}.changeStatus`),
});

const { loading } = usePageLoading();
const formOpen = ref(false);

const views = [
    { value: 'board', label: 'Board' },
    { value: 'list', label: 'List' },
];

const roleTones = { owner: 'indigo', admin: 'sky', member: 'gray' };

const hasFilters = useActiveFilters(toRef(props, 'filters'));

const progressLabel = computed(() => (props.progress.total === 0
    ? 'No tasks yet'
    : `${props.progress.done} of ${props.progress.total} tasks done · ${props.progress.percent}%`));

const emptyTitle = computed(() => (hasFilters.value
    ? 'No task matches the filters'
    : 'No tasks yet'));

const emptyDescription = computed(() => (hasFilters.value
    ? 'Clear the filters to see the rest of the project.'
    : 'Create the first task of the project to get going.'));

function canChangeTask(task) {
    return can(`tasks.${task.id}.changeStatus`);
}

function activeFilters() {
    return Object.fromEntries(
        Object.entries(props.filters ?? {}).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

function switchView(view) {
    if (view === props.view) {
        return;
    }

    router.get(`/projects/${props.project.id}`, { ...activeFilters(), ...(view === 'board' ? {} : { view }) }, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
  <Head :title="project.name" />

  <div class="mx-auto w-full max-w-5xl px-6 py-10">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-3xl font-bold tracking-tight text-content">
          {{ project.name }}
        </h1>
        <p class="mt-2 text-content-muted">
          {{ project.description ?? 'No description.' }}
        </p>
      </div>

      <Button
        v-if="can('tasks.create')"
        data-test="task-create"
        @click="formOpen = true"
      >
        New task
      </Button>
    </div>

    <section class="mt-6 rounded-panel border border-line bg-raised p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p
          data-test="project-progress"
          class="text-sm font-medium text-content"
        >
          {{ progressLabel }}
        </p>

        <ul class="flex flex-wrap gap-2">
          <li
            v-for="member in members"
            :key="member.id"
            data-test="project-member"
            class="flex items-center gap-2 rounded-panel border border-line bg-canvas px-2 py-1"
          >
            <Avatar
              size="sm"
              :name="member.name"
            />
            <span class="text-xs font-medium text-content">{{ member.name }}</span>
            <Badge :tone="roleTones[member.role] ?? 'gray'">
              {{ member.role }}
            </Badge>
          </li>
        </ul>
      </div>

      <div
        data-test="project-progress-bar"
        role="progressbar"
        :aria-label="`${progress.percent}% of the tasks are done`"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="progress.percent"
        class="mt-3 h-2 w-full overflow-hidden rounded-full bg-sunken"
      >
        <div
          class="h-full rounded-full bg-brand-500 transition-[width]"
          :style="{ width: `${progress.percent}%` }"
        />
      </div>
    </section>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
      <div
        data-test="view-toggle"
        class="inline-flex rounded-control border border-line-strong bg-raised p-0.5"
      >
        <button
          v-for="option in views"
          :key="option.value"
          type="button"
          :data-test="`view-${option.value}`"
          :aria-pressed="view === option.value"
          class="rounded px-3 py-1.5 text-sm font-medium transition-colors"
          :class="view === option.value
            ? 'bg-brand-600 text-content-inverted'
            : 'text-content-muted hover:bg-sunken'"
          @click="switchView(option.value)"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="w-full sm:w-auto">
        <TaskFilterForm
          :url="`/projects/${project.id}`"
          :filters="filters"
          :statuses="filterOptions.statuses"
          :priorities="filterOptions.priorities"
          :assignees="filterOptions.assignees"
        />
      </div>
    </div>

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

    <div
      v-if="loading"
      data-test="board-skeleton"
      class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5"
    >
      <div
        v-for="column in statuses"
        :key="column.value"
        class="rounded-panel border border-line p-3"
      >
        <Skeleton class="h-3 w-20" />
        <Skeleton class="mt-3 h-16 w-full" />
        <Skeleton class="mt-2 h-16 w-full" />
      </div>
    </div>

    <template v-else>
      <TaskBoard
        v-if="view === 'board'"
        :tasks="tasks"
        :statuses="statuses"
        :can-change="canChangeTask"
        :is-pending="isPending"
        :empty-title="emptyTitle"
        :empty-description="emptyDescription"
        @move="changeStatus"
      />

      <TaskList
        v-else
        :tasks="tasks"
        :empty-title="emptyTitle"
        :empty-description="emptyDescription"
      />

      <TaskPagination :links="tasks.links ?? []" />
    </template>

    <ActivityTimeline :activity="activity" />
  </div>

  <TaskFormModal
    v-model="formOpen"
    :project-id="project.id"
    :priorities="filterOptions.priorities"
  />
</template>
