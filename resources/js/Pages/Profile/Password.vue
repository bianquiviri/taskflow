<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import FormInput from '../../Components/FormInput.vue';

const visible = ref(false);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function save() {
    form.put('/profile/password', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            visible.value = false;
        },
    });
}
</script>

<template>
  <Head title="Change password" />

  <div class="mx-auto w-full max-w-3xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-content">
          Change password
        </h1>
        <p class="mt-1 text-sm text-content-muted">
          Confirm your current password to pick a new one.
        </p>
      </div>
      <Link
        data-test="to-profile"
        href="/profile"
        class="text-sm font-medium text-brand-text hover:underline"
      >
        Back to the profile
      </Link>
    </div>

    <section class="mt-8 rounded-panel border border-line bg-raised p-5 shadow-sm">
      <form
        data-test="password-form"
        class="max-w-md space-y-4"
        @submit.prevent="save"
      >
        <FormInput
          v-model="form.current_password"
          name="current_password"
          type="password"
          label="Current password"
          autocomplete="current-password"
          :error="form.errors.current_password"
          :disabled="form.processing"
        />

        <FormInput
          v-model="form.password"
          name="password"
          :type="visible ? 'text' : 'password'"
          label="New password"
          autocomplete="new-password"
          :error="form.errors.password"
          :disabled="form.processing"
        />

        <FormInput
          v-model="form.password_confirmation"
          name="password_confirmation"
          :type="visible ? 'text' : 'password'"
          label="Confirm new password"
          autocomplete="new-password"
          :error="form.errors.password_confirmation"
          :disabled="form.processing"
        />

        <label class="flex items-center gap-2 text-sm text-content-muted">
          <input
            v-model="visible"
            data-test="toggle-password"
            type="checkbox"
            class="size-4 rounded border-line-strong text-brand-600 dark:text-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-raised"
          >
          Show the new password
        </label>

        <p class="text-xs text-content-subtle">
          Changing your password signs out every device that remembered it.
        </p>

        <Button
          type="submit"
          :loading="form.processing"
        >
          Update password
        </Button>
      </form>
    </section>
  </div>
</template>
