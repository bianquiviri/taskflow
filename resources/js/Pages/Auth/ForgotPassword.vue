<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import FormInput from '../../Components/FormInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import AuthStatusMessage from './AuthStatusMessage.vue';

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
    <h1 class="text-2xl font-bold tracking-tight text-content">
      Reset your password
    </h1>
    <p class="mt-2 text-sm leading-6 text-content-muted">
      Enter your email address and we’ll send you a secure reset link.
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
        label="Email address"
        autocomplete="email"
        autofocus
        required
        :error="form.errors.email"
        :disabled="form.processing"
      />

      <Button
        type="submit"
        class="w-full"
        :loading="form.processing"
      >
        Email password reset link
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
