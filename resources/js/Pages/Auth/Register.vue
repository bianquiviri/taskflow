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
    <h1 class="text-2xl font-bold tracking-tight text-content">
      Create your account
    </h1>
    <p class="mt-2 text-sm text-content-muted">
      Start organizing your projects with TaskFlow.
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
        v-model="form.name"
        name="name"
        label="Full name"
        autocomplete="name"
        autofocus
        required
        :error="form.errors.name"
        :disabled="form.processing"
      />

      <FormInput
        v-model="form.email"
        name="email"
        type="email"
        label="Email address"
        autocomplete="email"
        required
        :error="form.errors.email"
        :disabled="form.processing"
      />

      <PasswordField
        v-model="form.password"
        name="password"
        label="Password"
        hint="Use at least 8 characters."
        :minlength="8"
        autocomplete="new-password"
        :error="form.errors.password"
        :disabled="form.processing"
      />

      <PasswordField
        v-model="form.password_confirmation"
        name="password_confirmation"
        label="Confirm password"
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
        Create account
      </Button>
    </form>

    <p class="mt-6 text-center text-sm text-content-muted">
      Already have an account?
      <Link
        href="/login"
        class="font-semibold text-brand-text hover:underline"
      >
        Sign in
      </Link>
    </p>
  </AuthLayout>
</template>
