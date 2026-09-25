<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({});

function submit() {
    form.post('/email/verification-notification');
}
</script>

<template>
  <Head title="Verify email" />

  <AuthLayout>
    <div class="flex size-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
      <svg
        class="size-6"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        aria-hidden="true"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
        />
      </svg>
    </div>

    <h1 class="mt-6 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
      Verify your email address
    </h1>
    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
      We sent a verification link to your inbox. Select it to activate your TaskFlow account.
    </p>

    <div
      v-if="status"
      class="mt-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
      role="status"
    >
      {{ status }}
    </div>

    <form
      class="mt-8"
      @submit.prevent="submit"
    >
      <button
        type="submit"
        :disabled="form.processing"
        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {{ form.processing ? 'Sending link…' : 'Resend verification email' }}
      </button>
    </form>

    <div class="mt-4 text-center">
      <Link
        href="/logout"
        method="post"
        as="button"
        type="button"
        class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
      >
        Sign out
      </Link>
    </div>

    <p class="mt-6 text-center text-xs leading-5 text-gray-500 dark:text-gray-400">
      The link can take a minute to arrive. Check your spam folder if it does not appear.
    </p>
  </AuthLayout>
</template>
