<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import AuthStatusMessage from './AuthStatusMessage.vue';
import PasswordField from './PasswordField.vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
    status: { type: String, default: null },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password');
}
</script>

<template>
  <Head title="Reset password" />

  <AuthLayout>
    <h1 class="text-2xl font-bold tracking-tight text-content">
      Choose a new password
    </h1>
    <p class="mt-2 text-sm leading-6 text-content-muted">
      <template v-if="email">
        Pick a new password for
        <span class="font-semibold text-content">{{ email }}</span>.
      </template>
      <template v-else>
        Pick a new password for your account.
      </template>
    </p>

    <AuthStatusMessage
      class="mt-6"
      :status="status"
    />

    <form
      class="mt-6 space-y-4"
      @submit.prevent="submit"
    >
      <input
        v-model="form.token"
        name="token"
        type="hidden"
      >
      <input
        v-model="form.email"
        name="email"
        type="hidden"
      >

      <PasswordField
        v-model="form.password"
        name="password"
        label="New password"
        hint="Use at least 8 characters and choose something unique."
        :minlength="8"
        autocomplete="new-password"
        autofocus
        :error="form.errors.password"
        :disabled="form.processing"
      />

      <PasswordField
        v-model="form.password_confirmation"
        name="password_confirmation"
        label="Confirm new password"
        :minlength="8"
        autocomplete="new-password"
        :error="form.errors.password_confirmation"
        :disabled="form.processing"
      />

      <Button
        type="submit"
        class="mt-2 w-full"
        :loading="form.processing"
      >
        Reset password
      </Button>
    </form>

    <p class="mt-6 text-center text-sm text-content-muted">
      <Link
        href="/login"
        class="font-semibold text-brand-text hover:underline"
      >
        Return to sign in
      </Link>
    </p>
  </AuthLayout>
</template>
