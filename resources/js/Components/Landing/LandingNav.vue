<script setup>
import { Link } from '@inertiajs/vue3';
import Button from '../Button.vue';
import Icon from '../Icon.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
});
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-line bg-canvas/80 backdrop-blur">
    <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
      <Link
        href="/"
        class="flex items-center gap-2.5 text-content"
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

      <nav
        class="flex items-center gap-2"
        aria-label="Primary"
      >
        <Button
          href="#signin"
          variant="ghost"
        >
          Sign in
        </Button>
        <Button href="/register">
          Get started
        </Button>
      </nav>
    </div>
  </header>
</template>
