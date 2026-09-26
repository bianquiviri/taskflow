<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Avatar from '../../Components/Avatar.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    project: { type: Object, required: true },
    activity: { type: Object, required: true },
});

const eventConfig = {
    'project.created': { label: 'created the project', icon: 'plus' },
    'project.updated': { label: 'updated the project', icon: 'projects' },
    'project.archived': { label: 'archived the project', icon: 'warning' },
};

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

const timestampFormatter = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

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

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
      {{ project.name }}
    </h1>
    <p class="mt-4 text-gray-600 dark:text-gray-300">
      {{ project.description ?? 'No description.' }}
    </p>

    <section class="mt-10">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
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
          class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
        >
          <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
            <Icon
              :name="entry.icon"
              class="size-4"
            />
          </span>
          <Avatar
            size="sm"
            :name="entry.actor"
          />
          <p class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200">
            <span class="font-medium text-gray-900 dark:text-white">{{ entry.actor }}</span>
            {{ entry.label }}
          </p>
          <time
            :datetime="entry.atIso"
            class="shrink-0 text-xs text-gray-500 dark:text-gray-400"
          >
            {{ entry.at }}
          </time>
        </li>
      </ol>
    </section>
  </div>
</template>
