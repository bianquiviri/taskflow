<script setup>
import { Link } from '@inertiajs/vue3';
import Badge from '../Badge.vue';
import EmptyState from '../EmptyState.vue';

defineProps({
    projects: { type: Array, required: true },
});

function tasks(count) {
    return `${count} ${count === 1 ? 'task' : 'tasks'}`;
}
</script>

<template>
  <section
    data-test="recent-projects"
    class="rounded-panel border border-line bg-raised p-5 shadow-sm"
  >
    <header class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-semibold text-content">
        Recent projects
      </h2>
      <Link
        data-test="recent-projects-all"
        href="/projects"
        class="text-xs font-medium text-brand-text hover:underline"
      >
        View all
      </Link>
    </header>

    <EmptyState
      v-if="projects.length === 0"
      class="mt-4"
      icon="projects"
      title="No projects yet"
      description="Create your first project to start tracking work."
    >
      <template #action>
        <Link
          data-test="recent-project-action"
          href="/projects"
          class="inline-flex min-h-10 items-center rounded-control bg-brand-600 px-4 py-2 text-sm font-semibold text-content-inverted"
        >
          New project
        </Link>
      </template>
    </EmptyState>

    <ul
      v-else
      class="mt-4 divide-y divide-line"
    >
      <li
        v-for="project in projects"
        :key="project.id"
        data-test="recent-project"
        class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0"
      >
        <Link
          data-test="recent-project-link"
          :href="`/projects/${project.id}`"
          class="font-medium text-content hover:text-brand-text"
        >
          {{ project.name }}
        </Link>

        <div class="flex items-center gap-2">
          <Badge
            data-test="recent-project-open"
            tone="sky"
          >
            {{ project.open }} open
          </Badge>
          <Badge
            data-test="recent-project-tasks"
            tone="gray"
          >
            {{ tasks(project.tasks) }}
          </Badge>
        </div>
      </li>
    </ul>
  </section>
</template>
