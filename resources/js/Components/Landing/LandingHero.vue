<script setup>
import Badge from '../Badge.vue';
import Button from '../Button.vue';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
});
</script>

<template>
  <div class="max-w-xl">
    <Badge>Projects · Tasks · Teams</Badge>

    <h1 class="mt-5 text-4xl font-bold tracking-tight text-content sm:text-5xl">
      Plan the work, track the progress, ship it.
    </h1>

    <p class="mt-5 text-lg leading-relaxed text-content-muted">
      {{ appName }} keeps your projects, tasks, deadlines and discussions in one
      place — so everyone always knows what to do next.
    </p>

    <div class="mt-8 flex flex-wrap items-center gap-3">
      <Button href="/register">
        Create a free account
      </Button>
      <Button
        href="#features"
        variant="secondary"
      >
        See what's inside
      </Button>
    </div>

    <p class="mt-6 text-sm text-content-subtle">
      No credit card required. Sign in on the right if you already have an
      account.
    </p>
  </div>
</template>
