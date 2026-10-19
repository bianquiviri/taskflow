<script setup>
import { Link } from '@inertiajs/vue3';
import LoginForm from '../Auth/LoginForm.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
});
</script>

<template>
  <section
    id="signin"
    aria-labelledby="signin-heading"
    class="w-full scroll-mt-24 rounded-shell border border-line bg-raised p-6 shadow-sm sm:p-8"
  >
    <h2
      id="signin-heading"
      class="text-xl font-bold tracking-tight text-content"
    >
      Sign in to {{ appName }}
    </h2>
    <p class="mt-1.5 text-sm text-content-muted">
      Already have an account? Pick up right where you left off.
    </p>

    <LoginForm class="mt-6" />

    <p class="mt-6 border-t border-line pt-6 text-center text-sm text-content-muted">
      New to {{ appName }}?
      <Link
        href="/register"
        class="font-semibold text-brand-text hover:underline"
      >
        Create an account
      </Link>
    </p>
  </section>
</template>
