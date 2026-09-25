<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
});

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
  <Head title="Forgot password" />

  <AuthLayout>
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
      Reset your password
    </h1>
    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
      Enter your email address and we’ll send you a secure reset link.
    </p>

    <div
      v-if="status"
      class="mt-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
      role="status"
    >
      {{ status }}
    </div>

    <form
      class="mt-8 space-y-5"
      @submit.prevent="submit"
    >
      <div>
        <label
          for="email"
          class="block text-sm font-medium text-gray-700 dark:text-gray-200"
        >Email</label>
        <input
          id="email"
          v-model="form.email"
          name="email"
          type="email"
          autocomplete="email"
          autofocus
          required
          class="mt-2 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >
        <p
          v-if="form.errors.email"
          class="mt-1.5 text-sm text-rose-600 dark:text-rose-400"
        >
          {{ form.errors.email }}
        </p>
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {{ form.processing ? 'Sending link…' : 'Email password reset link' }}
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
      <Link
        href="/login"
        class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
      >
        Return to sign in
      </Link>
    </p>
  </AuthLayout>
</template>
