<script setup>
import { Link } from '@inertiajs/vue3';
import Button from '../Button.vue';
import Icon from '../Icon.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
    version: { type: String, default: '' },
    showCtas: { type: Boolean, default: true },
});
</script>

<template>
  <footer class="border-t border-line bg-sunken py-16">
    <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
      <div class="flex flex-col gap-8 sm:flex-row sm:items-center sm:justify-between">
        <Link
          href="/"
          class="flex w-fit items-center gap-2.5 text-content"
          :aria-label="`${appName} home`"
        >
          <span class="flex size-9 items-center justify-center rounded-control bg-brand-600 text-content-inverted shadow-sm">
            <Icon
              name="logo"
              class="size-5"
            />
          </span>
          <span class="text-lg font-bold tracking-tight">{{ appName }}</span>
        </Link>

        <div
          v-if="showCtas"
          class="flex flex-wrap items-center gap-3"
        >
          <Button
            href="#signin"
            variant="secondary"
          >
            Sign in
          </Button>
          <Button href="/register">
            Create an account
          </Button>
        </div>
      </div>

      <div class="mt-10 flex flex-col gap-2 border-t border-line pt-6 text-sm text-content-subtle sm:flex-row sm:items-center sm:justify-between">
        <p>
          {{ appName }} — project &amp; task management for teams.
        </p>
        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span>
            Developed by

            <a
              href="https://github.com/bianquiviri"
              class="font-medium text-content-muted underline-offset-2 hover:text-content hover:underline"
              target="_blank"
              rel="noopener noreferrer"
            >Jose Antonio Bianco</a>
          </span>
          <span aria-hidden="true">·</span>
          <a
            href="https://creativecommons.org/licenses/by-nc-nd/4.0/"
            class="underline-offset-2 hover:text-content hover:underline"
            target="_blank"
            rel="license noopener noreferrer"
          >CC BY-NC-ND 4.0</a>
          <span v-if="version">· {{ version }}</span>
        </p>
      </div>
    </div>
  </footer>
</template>
