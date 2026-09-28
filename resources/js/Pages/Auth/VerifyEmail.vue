<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import Icon from '../../Components/Icon.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import AuthStatusMessage from './AuthStatusMessage.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({});

function submit() {
    form.post('/email/verification-notification');
}
</script>

<template>
  <Head title="Verify email" />

  <AuthLayout>
    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand-text">
      <Icon
        name="user"
        aria-hidden="true"
        class="size-6"
      />
    </div>

    <h1 class="mt-5 text-center text-2xl font-bold tracking-tight text-content">
      Verify your email address
    </h1>
    <p class="mt-2 text-center text-sm leading-6 text-content-muted">
      We sent a verification link to your inbox. Select it to activate your TaskFlow account.
    </p>

    <AuthStatusMessage
      class="mt-6"
      :status="status"
    />

    <form
      class="mt-6"
      @submit.prevent="submit"
    >
      <Button
        type="submit"
        class="w-full"
        :loading="form.processing"
      >
        Resend verification email
      </Button>
    </form>

    <div class="mt-4 text-center">
      <Link
        href="/logout"
        method="post"
        as="button"
        type="button"
        class="text-sm font-medium text-content-muted hover:text-content hover:underline"
      >
        Sign out
      </Link>
    </div>

    <p class="mt-6 text-center text-xs leading-5 text-content-subtle">
      The link can take a minute to arrive. Check your spam folder if it does not appear.
    </p>
  </AuthLayout>
</template>
