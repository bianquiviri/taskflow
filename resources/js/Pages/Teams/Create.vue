<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import FormInput from '../../Components/FormInput.vue';

const form = useForm({
    name: '',
});

function submit() {
    form.post('/teams', { preserveScroll: true });
}
</script>

<template>
  <Head title="Create team" />

  <div class="mx-auto w-full max-w-3xl px-6 py-10">
    <div>
      <h1 class="text-3xl font-bold tracking-tight text-content">
        Create your team
      </h1>
      <p class="mt-1 text-sm text-content-muted">
        A team is the workspace that gathers your projects and the people you invite.
      </p>
    </div>

    <section class="mt-6 rounded-panel border border-line bg-raised p-5 shadow-sm">
      <form
        data-test="create-team-form"
        class="space-y-4"
        @submit.prevent="submit"
      >
        <FormInput
          v-model="form.name"
          name="name"
          label="Team name"
          autocomplete="organization"
          :error="form.errors.name"
          :disabled="form.processing"
        />

        <div class="flex items-center gap-3">
          <Button
            type="submit"
            :loading="form.processing"
          >
            Create team
          </Button>
          <Link
            data-test="cancel-create-team"
            href="/dashboard"
            class="text-sm font-medium text-content-muted hover:text-content"
          >
            Cancel
          </Link>
        </div>
      </form>
    </section>
  </div>
</template>
