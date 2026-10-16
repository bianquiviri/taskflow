<script setup>
import Button from '../Button.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
    version: { type: String, default: '' },
    showCtas: { type: Boolean, default: true },
});
</script>

<template>
  <footer
    class="border-t border-line bg-sunken"
    :class="showCtas ? 'py-16' : 'py-5'"
  >
    <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
      <div
        v-if="showCtas"
        class="mb-10 flex flex-wrap items-center gap-3 sm:justify-end"
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

      <div
        class="flex flex-col gap-2 text-sm text-content-subtle sm:flex-row sm:items-center sm:justify-between"
        :class="showCtas ? 'border-t border-line pt-6' : ''"
      >
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
