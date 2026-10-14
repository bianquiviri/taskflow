<script setup>
import Icon from '../Icon.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
});

const features = [
    {
        icon: 'projects',
        title: 'Projects',
        description: 'Group related work into a project with a deadline, a description and the people who own it.',
    },
    {
        icon: 'tasks',
        title: 'Tasks & boards',
        description: 'Break a project down into tasks, assign owners and priorities, then move them across a board as they progress.',
    },
    {
        icon: 'user',
        title: 'Teams & invitations',
        description: 'Invite teammates by email and give each one the role their work needs.',
    },
    {
        icon: 'bell',
        title: 'Comments & mentions',
        description: 'Discuss the work where it happens and @mention whoever needs to know.',
    },
    {
        icon: 'info',
        title: 'Activity trail',
        description: 'Every change is written to an audit trail, so you can always see who did what and when.',
    },
    {
        icon: 'home',
        title: 'Dashboard & metrics',
        description: 'See what is overdue, what is in flight and how each project is progressing at a glance.',
    },
];
</script>

<template>
  <section
    id="features"
    class="scroll-mt-24 border-t border-line bg-sunken py-16 sm:py-24"
  >
    <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
      <h2 class="max-w-2xl text-3xl font-bold tracking-tight text-content sm:text-4xl">
        What is {{ appName }}?
      </h2>
      <p class="mt-4 max-w-2xl text-lg leading-relaxed text-content-muted">
        A single workspace for the whole life of a piece of work — from the first
        idea to the day it ships. No more scattered spreadsheets, chat threads and
        reminders.
      </p>

      <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <li
          v-for="feature in features"
          :key="feature.title"
          class="rounded-shell border border-line bg-raised p-6"
        >
          <span class="flex size-10 items-center justify-center rounded-control bg-brand-soft text-brand-text">
            <Icon
              :name="feature.icon"
              class="size-5"
            />
          </span>
          <h3 class="mt-4 text-base font-semibold text-content">
            {{ feature.title }}
          </h3>
          <p class="mt-1.5 text-sm leading-relaxed text-content-muted">
            {{ feature.description }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
