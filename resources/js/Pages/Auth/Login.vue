<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LoginForm from '../../Components/Auth/LoginForm.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import AuthStatusMessage from './AuthStatusMessage.vue';

defineProps({
    status: { type: String, default: null },
});
</script>

<template>
  <Head title="Sign in" />

  <AuthLayout>
    <h1 class="text-2xl font-bold tracking-tight text-content">
      Welcome back
    </h1>
    <p class="mt-2 text-sm text-content-muted">
      Sign in to continue to your projects.
    </p>

    <AuthStatusMessage
      class="mt-6"
      :status="status"
    />

    <LoginForm
      class="mt-6"
      autofocus
    />

    <p class="mt-6 text-center text-sm text-content-muted">
      New to TaskFlow?
      <Link
        href="/register"
        class="font-semibold text-brand-text hover:underline"
      >
        Create an account
      </Link>
    </p>
  </AuthLayout>
</template>
