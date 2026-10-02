<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import FormInput from '../../Components/FormInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import AuthStatusMessage from './AuthStatusMessage.vue';
import PasswordField from './PasswordField.vue';

defineProps({
    status: { type: String, default: null },
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

    <form
      class="mt-6 space-y-4"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="form.email"
        name="email"
        type="email"
        label="Email"
        autocomplete="email"
        autofocus
        required
        :error="form.errors.email"
        :disabled="form.processing"
      />

      <PasswordField
        v-model="form.password"
        name="password"
        label="Password"
        autocomplete="current-password"
        :error="form.errors.password"
        :disabled="form.processing"
      />

      <div class="flex items-center justify-between gap-4">
        <label class="flex items-center gap-2 text-sm text-content-muted">
          <input
            v-model="form.remember"
            name="remember"
            type="checkbox"
            :disabled="form.processing"
            class="size-4 rounded border-line-strong text-brand-600 dark:text-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-raised"
          >
          Remember me
        </label>

        <Link
          href="/forgot-password"
          class="text-sm font-medium text-brand-text hover:underline"
        >
          Forgot password?
        </Link>
      </div>

      <Button
        type="submit"
        class="mt-2 w-full"
        :loading="form.processing"
      >
        Sign in
      </Button>
    </form>

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
