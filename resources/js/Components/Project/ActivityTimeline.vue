<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '../Avatar.vue';
import EmptyState from '../EmptyState.vue';
import Icon from '../Icon.vue';

const props = defineProps({
    activity: { type: Object, required: true },
});

const dayFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });

const weekdayFormatter = new Intl.DateTimeFormat('en-GB', { weekday: 'short' });

const timeFormatter = new Intl.DateTimeFormat('en-GB', { timeStyle: 'short' });

const entries = computed(() => props.activity?.data ?? []);

const days = computed(() => {
    const groups = [];

    for (const entry of entries.value) {
        const current = groups[groups.length - 1];

        if (current?.day === entry.day) {
            current.entries.push(entry);

            continue;
        }

        groups.push({ day: entry.day, label: formatDay(entry.day), entries: [entry] });
    }

    return groups;
});

const hasSeveralPages = computed(() => (props.activity?.last_page ?? 1) > 1);

function formatDay(day) {
    const date = new Date(`${day}T00:00:00`);

    return `${weekdayFormatter.format(date)} ${dayFormatter.format(date)}`;
}

function formatTime(value) {
    return timeFormatter.format(new Date(value));
}
</script>

<template>
  <section
    data-test="activity-timeline"
    class="mt-12"
  >
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
        description="Changes to this project and its tasks will show up here."
      />
    </div>

    <div
      v-else
      class="mt-4 space-y-6"
    >
      <section
        v-for="day in days"
        :key="day.day"
        data-test="activity-day"
      >
        <h3
          data-test="activity-day-label"
          class="text-xs font-semibold uppercase tracking-wide text-content-subtle"
        >
          {{ day.label }}
        </h3>

        <ol class="mt-2 space-y-3">
          <li
            v-for="entry in day.entries"
            :key="entry.id"
            data-test="activity-entry"
            class="flex flex-wrap items-center gap-3 rounded-panel border border-line bg-raised px-4 py-3"
          >
            <span
              data-test="activity-icon"
              :data-icon="entry.icon"
              aria-hidden="true"
              class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text"
            >
              <Icon
                :name="entry.icon"
                class="size-4"
              />
            </span>
            <Avatar
              size="sm"
              :name="entry.actor.name"
            />
            <p class="min-w-0 flex-1 truncate text-sm text-content-muted">
              <span class="font-medium text-content">{{ entry.actor.name }}</span>
              {{ entry.label }}
            </p>
            <Link
              v-if="entry.target.url"
              data-test="activity-target"
              :href="entry.target.url"
              class="max-w-48 shrink-0 truncate rounded-control bg-sunken px-2 py-1 text-xs font-medium text-content hover:bg-brand-soft hover:text-brand-text"
            >
              {{ entry.target.title }}
            </Link>
            <span
              v-else
              data-test="activity-target"
              class="max-w-48 shrink-0 truncate rounded-control bg-sunken px-2 py-1 text-xs font-medium text-content"
            >
              {{ entry.target.title }}
            </span>
            <time
              data-test="activity-time"
              :datetime="entry.at"
              class="shrink-0 text-xs text-content-subtle"
            >
              {{ formatTime(entry.at) }}
            </time>
          </li>
        </ol>
      </section>

      <nav
        v-if="hasSeveralPages"
        data-test="activity-pagination"
        aria-label="Activity pages"
        class="flex flex-wrap items-center justify-end gap-2"
      >
        <Link
          v-if="activity.prev_page_url"
          data-test="activity-previous"
          :href="activity.prev_page_url"
          preserve-scroll
          preserve-state
          class="rounded-control border border-line-strong bg-raised px-3 py-1.5 text-sm font-medium text-content-muted hover:bg-sunken hover:text-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-raised"
        >
          Newer
        </Link>
        <span
          data-test="activity-page"
          role="status"
          class="text-xs text-content-subtle"
        >
          Page {{ activity.current_page }} of {{ activity.last_page }}
        </span>
        <Link
          v-if="activity.next_page_url"
          data-test="activity-next"
          :href="activity.next_page_url"
          preserve-scroll
          preserve-state
          class="rounded-control border border-line-strong bg-raised px-3 py-1.5 text-sm font-medium text-content-muted hover:bg-sunken hover:text-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-raised"
        >
          Older
        </Link>
      </nav>
    </div>
  </section>
</template>
