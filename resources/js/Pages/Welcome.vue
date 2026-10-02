<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: { type: String, required: true },
    version: { type: String, required: true },
});
</script>

<template>
  <Head title="Home" />

  <div class="flex min-h-dvh flex-col items-center justify-center px-6">
    <main class="mx-auto w-full max-w-2xl rounded-shell border border-line bg-raised p-8 shadow-sm">
      <h1 class="text-3xl font-bold tracking-tight text-content">
        {{ appName }}
      </h1>
      <p class="mt-4 text-lg leading-relaxed text-content-muted">
        Professional project management platform built with Laravel 13, Inertia and Vue 3.
      </p>

      <div class="mt-8 rounded-panel bg-sunken p-4 text-sm text-content-subtle">
        <p><span class="font-semibold text-content-muted">Framework:</span> Laravel</p>
        <p><span class="font-semibold text-content-muted">Version:</span> {{ version }}</p>
      </div>
    </main>
  </div>
</template>