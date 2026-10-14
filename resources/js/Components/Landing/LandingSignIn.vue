<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Button from '../Button.vue';
import FormInput from '../FormInput.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login');
}
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

    <form
      class="mt-6 space-y-4"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="form.email"
        label="Email"
        type="email"
        name="email"
        autocomplete="email"
        :error="form.errors.email"
        :disabled="form.processing"
      />

      <FormInput
        v-model="form.password"
        label="Password"
        type="password"
        name="password"
        autocomplete="current-password"
        :error="form.errors.password"
        :disabled="form.processing"
      />

      <div class="flex items-center justify-between gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-content-muted">
          <input
            v-model="form.remember"
            type="checkbox"
            name="remember"
            class="size-4 rounded border-line-strong text-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
          >
          Remember me
        </label>

        <Link
          href="/forgot-password"
          class="text-sm font-semibold text-brand-text hover:underline"
        >
          Forgot password?
        </Link>
      </div>

      <Button
        type="submit"
        class="w-full"
        :loading="form.processing"
      >
        Sign in
      </Button>
    </form>

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
