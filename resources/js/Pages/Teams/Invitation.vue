<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Badge from '../../Components/Badge.vue';
import Button from '../../Components/Button.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    team: { type: Object, required: true },
    email: { type: String, default: null },
    role: { type: Object, required: true },
    expiresAt: { type: String, required: true },
    acceptUrl: { type: String, required: true },
    status: { type: String, required: true },
});

const page = usePage();

const tokenError = computed(() => page.props.errors?.token ?? null);

const formattedExpiry = computed(() =>
    new Intl.DateTimeFormat('en-GB', { dateStyle: 'long' }).format(new Date(props.expiresAt)),
);

const messages = {
    revoked: 'This invitation has been revoked by the team.',
    expired: 'This invitation has expired. Ask for a new one to join the team.',
    email_mismatch: 'This invitation was sent to another email address.',
    already_member: 'You already have a seat in this team.',
};

const notice = computed(() => messages[props.status] ?? null);

const canAccept = computed(() => props.status === 'valid' && !tokenError.value);

function accept() {
    router.post(props.acceptUrl, { preserveScroll: true });
}
</script>

<template>
  <Head :title="`Join ${team.name}`" />

  <div class="mx-auto w-full max-w-2xl px-6 py-10">
    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
      Join {{ team.name }}
    </h1>

    <section
      data-test="invitation-card"
      class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
      <p class="text-sm text-gray-600 dark:text-gray-300">
        You have been invited to join
        <span class="font-semibold text-gray-900 dark:text-white">{{ team.name }}</span>
        as
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-3">
        <Badge :tone="role.tone">
          {{ role.label }}
        </Badge>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ email }} · expires on {{ formattedExpiry }}
        </p>
      </div>

      <p
        v-if="notice"
        data-test="invitation-notice"
        class="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
      >
        <Icon
          name="warning"
          class="mt-0.5 size-4 shrink-0"
        />
        {{ notice }}
      </p>

      <p
        v-if="tokenError"
        data-test="token-error"
        class="mt-4 text-sm text-rose-600 dark:text-rose-400"
        role="alert"
      >
        {{ tokenError }}
      </p>

      <div class="mt-6 flex items-center gap-3">
        <Button
          v-if="canAccept"
          data-test="accept-button"
          :loading="page.props.progress"
          @click="accept"
        >
          Accept invitation
        </Button>
        <Link
          v-if="status === 'already_member'"
          class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
          :href="`/teams/${team.id}`"
        >
          Open the team
        </Link>
        <Link
          class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
          href="/projects"
        >
          Not now
        </Link>
      </div>
    </section>
  </div>
</template>
