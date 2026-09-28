<script setup>
import { computed, onMounted, onUnmounted, ref, toRef } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useCan } from '../../Composables/useCan';
import { useTaskStatusChange } from '../../Composables/useTaskStatusChange';
import Avatar from '../../Components/Avatar.vue';
import Badge from '../../Components/Badge.vue';
import Button from '../../Components/Button.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Icon from '../../Components/Icon.vue';
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

const loading = ref(false);
const formOpen = ref(false);
let stopStart = null;
let stopFinish = null;

const views = [
    { value: 'board', label: 'Board' },
    { value: 'list', label: 'List' },
];

const roleTones = { owner: 'indigo', admin: 'sky', member: 'gray' };

const hasFilters = computed(() =>
    Object.values(props.filters ?? {}).some((value) => value !== null && value !== undefined && value !== ''),
);

const progressLabel = computed(() => (props.progress.total === 0
    ? 'No tasks yet'
    : `${props.progress.done} of ${props.progress.total} tasks done · ${props.progress.percent}%`));

const emptyBoardTitle = computed(() => (hasFilters.value
    ? 'No task matches the filters'
    : 'No tasks on this board yet'));

const emptyBoardDescription = computed(() => (hasFilters.value
    ? 'Clear the filters to see the rest of the project.'
    : 'Create the first task to fill the board.'));

onMounted(() => {
    stopStart = router.on('start', (visit) => {
        if (visit?.method === 'get' && !(visit.only?.length > 0)) {
            loading.value = true;
        }
    });
    stopFinish = router.on('finish', () => {
        loading.value = false;
    });
});

onUnmounted(() => {
    stopStart?.();
    stopFinish?.();
});

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

const eventConfig = {
    'project.created': { label: 'created the project', icon: 'plus' },
    'project.updated': { label: 'updated the project', icon: 'projects' },
    'project.archived': { label: 'archived the project', icon: 'warning' },
};

const timestampFormatter = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const entries = computed(() =>
    (props.activity?.data ?? []).map((entry) => ({
        id: entry.id,
        icon: eventConfig[entry.event]?.icon ?? 'info',
        label: eventConfig[entry.event]?.label ?? humanize(entry.event),
        actor: entry.actor?.name ?? 'Unknown actor',
        at: formatTimestamp(entry.created_at),
        atIso: entry.created_at,
    })),
);

function formatTimestamp(value) {
    return timestampFormatter.format(new Date(value));
}

function humanize(value) {
    const phrase = String(value ?? '').replace(/[._-]+/g, ' ').trim();

    return phrase ? `${phrase.charAt(0).toUpperCase()}${phrase.slice(1)}` : 'Unknown event';
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
        :empty-title="emptyBoardTitle"
        :empty-description="emptyBoardDescription"
        @move="changeStatus"
      />

      <TaskList
        v-else
        :tasks="tasks"
      />

      <TaskPagination :links="tasks.links ?? []" />
    </template>

    <section class="mt-12">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-subtle">
        Activity
      </h2>

      <div
        v-if="entries.length === 0"
        data-test="activity-empty"
        class="mt-4"
      >
        <EmptyState
          icon="info"
          title="No activity yet"
          description="Changes to this project will show up here."
        />
      </div>

      <ol
        v-else
        class="mt-4 space-y-3"
      >
        <li
          v-for="entry in entries"
          :key="entry.id"
          data-test="activity-entry"
          class="flex items-center gap-3 rounded-panel border border-line bg-raised px-4 py-3"
        >
          <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text">
            <Icon
              :name="entry.icon"
              class="size-4"
            />
          </span>
          <Avatar
            size="sm"
            :name="entry.actor"
          />
          <p class="min-w-0 flex-1 truncate text-sm text-content-muted">
            <span class="font-medium text-content">{{ entry.actor }}</span>
            {{ entry.label }}
          </p>
          <time
            :datetime="entry.atIso"
            class="shrink-0 text-xs text-content-subtle"
          >
            {{ entry.at }}
          </time>
        </li>
      </ol>
    </section>
  </div>

  <TaskFormModal
    v-model="formOpen"
    :project-id="project.id"
    :priorities="filterOptions.priorities"
  />
</template>
