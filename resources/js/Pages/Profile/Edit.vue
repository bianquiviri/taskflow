<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import FormInput from '../../Components/FormInput.vue';
import AvatarField from './AvatarField.vue';
import ThemeChoice from './ThemeChoice.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
        validator: (user) => ['name', 'email', 'theme', 'avatar', 'emailVerified']
            .every((key) => key in user),
    },
});

const theme = ref(props.user.theme);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
});

// Inertia turns the payload into multipart data on its own as soon as the
// value is a File.
const avatar = useForm({ avatar: null });

function save() {
    form.patch('/profile', { preserveScroll: true });
}

function upload(file) {
    avatar.avatar = file;

    avatar.post('/profile/avatar', {
        preserveScroll: true,
        onSuccess: () => avatar.reset(),
    });
}

function remove() {
    avatar.delete('/profile/avatar', { preserveScroll: true });
}
</script>

<template>
  <Head title="Profile" />

  <div class="mx-auto w-full max-w-3xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-content">
          Profile
        </h1>
        <p class="mt-1 text-sm text-content-muted">
          Your photo, how you are known and how TaskFlow looks to you.
        </p>
      </div>
      <Link
        data-test="to-password"
        href="/profile/password"
        class="text-sm font-medium text-brand-text hover:underline"
      >
        Change password
      </Link>
    </div>

    <section
      v-if="!user.emailVerified"
      data-test="unverified"
      class="mt-6 rounded-panel border border-warning/40 bg-warning-soft p-4 text-sm text-warning-text"
      role="status"
    >
      <strong class="font-semibold">Confirm your email address.</strong>
      We sent a link to {{ user.email }}. Open it to use the rest of the app — you
      can correct the address here until then.
      <Link
        data-test="resend-verification"
        href="/email/verify"
        class="ml-1 font-semibold underline"
      >
        Resend the link
      </Link>
    </section>

    <section class="mt-8 rounded-panel border border-line bg-raised p-5 shadow-sm">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-muted">
        Photo
      </h2>

      <div class="mt-4">
        <AvatarField
          :name="user.name"
          :src="user.avatar"
          :error="avatar.errors.avatar"
          :processing="avatar.processing"
          @select="upload"
          @remove="remove"
        />
      </div>
    </section>

    <section class="mt-6 rounded-panel border border-line bg-raised p-5 shadow-sm">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-muted">
        Identity
      </h2>

      <form
        data-test="identity-form"
        class="mt-4 space-y-4"
        @submit.prevent="save"
      >
        <FormInput
          v-model="form.name"
          name="name"
          label="Full name"
          autocomplete="name"
          :error="form.errors.name"
          :disabled="form.processing"
        />

        <FormInput
          v-model="form.email"
          name="email"
          type="email"
          label="Email address"
          autocomplete="email"
          :error="form.errors.email"
          :disabled="form.processing"
        />

        <p class="text-xs text-content-subtle">
          Changing your address asks you to confirm it again before the account
          can be used.
        </p>

        <Button
          type="submit"
          :loading="form.processing"
        >
          Save changes
        </Button>
      </form>
    </section>

    <section class="mt-6 rounded-panel border border-line bg-raised p-5 shadow-sm">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-muted">
        Appearance
      </h2>

      <div class="mt-4">
        <ThemeChoice v-model="theme" />
      </div>
    </section>
  </div>
</template>
