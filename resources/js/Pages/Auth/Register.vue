<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register');
}
</script>

<template>
  <Head title="Create account" />

  <AuthLayout>
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
      Create your account
    </h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
      Start organizing your projects with TaskFlow.
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
          for="name"
          class="block text-sm font-medium text-gray-700 dark:text-gray-200"
        >Name</label>
        <input
          id="name"
          v-model="form.name"
          name="name"
          type="text"
          autocomplete="name"
          autofocus
          required
          class="mt-2 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >
        <p
          v-if="form.errors.name"
          class="mt-1.5 text-sm text-rose-600 dark:text-rose-400"
        >
          {{ form.errors.name }}
        </p>
      </div>

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

      <div>
        <label
          for="password"
          class="block text-sm font-medium text-gray-700 dark:text-gray-200"
        >Password</label>
        <input
          id="password"
          v-model="form.password"
          name="password"
          type="password"
          autocomplete="new-password"
          minlength="8"
          required
          class="mt-2 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >
        <p
          v-if="form.errors.password"
          class="mt-1.5 text-sm text-rose-600 dark:text-rose-400"
        >
          {{ form.errors.password }}
        </p>
      </div>

      <div>
        <label
          for="password_confirmation"
          class="block text-sm font-medium text-gray-700 dark:text-gray-200"
        >Confirm password</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          name="password_confirmation"
          type="password"
          autocomplete="new-password"
          minlength="8"
          required
          class="mt-2 block w-full rounded-lg border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {{ form.processing ? 'Creating account…' : 'Create account' }}
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
      Already have an account?
      <Link
        href="/login"
        class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
      >
        Sign in
      </Link>
    </p>
  </AuthLayout>
</template>
