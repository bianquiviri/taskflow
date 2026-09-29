<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Icon.vue';

const actions = [
    {
        key: 'projects',
        label: 'New project',
        hint: 'Create a project and invite your team.',
        href: '/projects',
        icon: 'projects',
    },
    {
        key: 'tasks',
        label: 'My tasks',
        hint: 'Everything assigned to you, soonest first.',
        href: '/tasks/mine',
        icon: 'tasks',
    },
];
</script>

<template>
  <section
    data-test="quick-actions"
    class="rounded-panel border border-line bg-raised p-5 shadow-sm"
  >
    <h2 class="text-sm font-semibold text-content">
      Quick actions
    </h2>

    <ul class="mt-4 space-y-3">
      <li
        v-for="action in actions"
        :key="action.key"
        data-test="quick-action"
      >
        <Link
          :data-test="`quick-action-${action.key}`"
          :href="action.href"
          class="flex items-center gap-3 rounded-control border border-line-strong bg-raised p-3 transition-colors hover:bg-sunken focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
        >
          <span class="flex size-9 shrink-0 items-center justify-center rounded-control bg-brand-soft text-brand-text">
            <Icon
              :name="action.icon"
              class="size-5"
            />
          </span>
          <span class="min-w-0">
            <span class="block text-sm font-semibold text-content">{{ action.label }}</span>
            <span
              data-test="quick-action-hint"
              class="block text-xs text-content-subtle"
            >{{ action.hint }}</span>
          </span>
        </Link>
      </li>
    </ul>
  </section>
</template>
