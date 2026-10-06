<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Icon.vue';

/**
 * The four steps that take a new account from an empty workspace to tracked
 * work: team, invitation, project, task. Every step carries the call to action
 * of the flow that really exists: there is no route to create a team, so that
 * step is stated plainly instead of linking to an invented destination, and the
 * task step falls back to the project listing while there is no project to
 * open. A step is only ticked on positive evidence the page already has; the
 * invitation is the one signal the dashboard cannot see, which is harmless
 * because the checklist is a first run panel that disappears with the first
 * project.
 */
const props = defineProps({
    hasTeam: { type: Boolean, default: false },
    hasProject: { type: Boolean, default: false },
    hasTask: { type: Boolean, default: false },
    invited: { type: Boolean, default: false },
    teamId: { type: [Number, String], default: null },
    projectId: { type: [Number, String], default: null },
});

const steps = computed(() => [
    {
        key: 'team',
        title: 'Create your team',
        description: 'A team is the workspace shared with everybody you invite.',
        done: props.hasTeam,
        cta: props.hasTeam && props.teamId ? { href: `/teams/${props.teamId}`, label: 'Open your team' } : null,
        unavailable: !props.hasTeam,
    },
    {
        key: 'invite',
        title: 'Invite a teammate',
        description: 'Send an invitation so somebody else can see the work.',
        done: props.invited,
        cta: props.teamId ? { href: `/teams/${props.teamId}`, label: 'Invite somebody' } : null,
        unavailable: false,
    },
    {
        key: 'project',
        title: 'Create your first project',
        description: 'A project gathers the tasks of one piece of work.',
        done: props.hasProject,
        cta: { href: '/projects', label: 'Go to projects' },
        unavailable: false,
    },
    {
        key: 'task',
        title: 'Add your first task',
        description: 'Break the project down into tasks on its board.',
        done: props.hasTask,
        cta: props.projectId
            ? { href: `/projects/${props.projectId}`, label: 'Open the board' }
            : { href: '/projects', label: 'Go to projects' },
        unavailable: false,
    },
]);

const finished = computed(() => steps.value.filter((step) => step.done).length);
</script>

<template>
  <section
    data-test="onboarding-checklist"
    class="rounded-panel border border-line bg-raised p-5 shadow-sm"
  >
    <header class="flex items-baseline justify-between gap-3">
      <h2 class="text-sm font-semibold text-content">
        Get started
      </h2>
      <p
        v-if="finished < steps.length"
        data-test="onboarding-progress"
        class="text-xs text-content-subtle"
      >
        {{ finished }} of {{ steps.length }} steps done
      </p>
    </header>

    <p
      v-if="finished === steps.length"
      data-test="onboarding-done"
      class="mt-4 text-sm text-content-muted"
    >
      Everything is set up.
    </p>

    <ol
      v-else
      class="mt-4 space-y-3"
    >
      <li
        v-for="(step, index) in steps"
        :key="step.key"
        data-test="onboarding-step"
        :data-step="step.key"
        :data-done="step.done ? 'true' : 'false'"
        class="flex flex-wrap items-start gap-3 rounded-panel border border-line bg-canvas px-4 py-3"
      >
        <span
          :data-test="step.done ? 'onboarding-step-done' : 'onboarding-step-pending'"
          class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
          :class="step.done ? 'bg-success-soft text-success-text' : 'bg-sunken text-content-subtle'"
        >
          <template v-if="step.done">
            <Icon
              name="check"
              class="size-4"
              aria-hidden="true"
            />
            <span class="sr-only">Done</span>
          </template>
          <span
            v-else
            aria-hidden="true"
          >{{ index + 1 }}</span>
        </span>

        <div class="min-w-0 flex-1">
          <p
            data-test="onboarding-step-title"
            class="text-sm font-medium text-content"
          >
            {{ step.title }}
          </p>
          <p
            v-if="step.description"
            class="mt-0.5 text-xs text-content-subtle"
          >
            {{ step.description }}
          </p>
          <p
            v-if="step.unavailable"
            data-test="onboarding-step-unavailable"
            class="mt-1 text-xs text-content-faint"
          >
            Creating a team is not available in the app yet.
          </p>
        </div>

        <Link
          v-if="step.cta"
          data-test="onboarding-step-cta"
          :href="step.cta.href"
          class="shrink-0 rounded-control border border-line-strong bg-raised px-3 py-1.5 text-xs font-semibold text-content-muted hover:bg-sunken hover:text-content"
        >
          {{ step.cta.label }}
        </Link>
      </li>
    </ol>
  </section>
</template>