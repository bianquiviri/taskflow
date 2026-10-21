<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Button from '../Button.vue';
import FormInput from '../FormInput.vue';
import PasswordField from './PasswordField.vue';

/*
| The one sign-in form, shared by the login page and the landing page, so both
| surfaces ask for the same credentials the same way and post to POST /login.
| The page keeps its own heading, status message and "create an account" link.
*/
defineProps({
    autofocus: { type: Boolean, default: false },
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
  <form
    class="space-y-4"
    @submit.prevent="submit"
  >
    <FormInput
      v-model="form.email"
      name="email"
      type="email"
      label="Email"
      autocomplete="email"
      :autofocus="autofocus"
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
      class="w-full"
      :loading="form.processing"
    >
      Sign in
    </Button>
  </form>
</template>
