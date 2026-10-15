<script setup>
import { Head } from '@inertiajs/vue3';
import LandingFeatures from '../Components/Landing/LandingFeatures.vue';
import LandingFooter from '../Components/Landing/LandingFooter.vue';
import LandingHero from '../Components/Landing/LandingHero.vue';
import LandingNav from '../Components/Landing/LandingNav.vue';
import LandingSignIn from '../Components/Landing/LandingSignIn.vue';
import LandingSteps from '../Components/Landing/LandingSteps.vue';

defineProps({
    appName: { type: String, required: true },
    version: { type: String, required: true },
});
</script>

<template>
  <Head title="Project & task management for teams" />

  <div class="min-h-dvh bg-canvas text-content">
    <LandingNav :app-name="appName" />

    <main>
      <section class="relative overflow-hidden">
        <div
          aria-hidden="true"
          class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-gradient-to-b from-brand-soft to-transparent"
        />

        <div class="relative mx-auto grid w-full max-w-6xl items-start gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:py-24">
          <LandingHero :app-name="appName" />
          <LandingSignIn :app-name="appName" />
        </div>
      </section>

      <LandingFeatures :app-name="appName" />
      <LandingSteps />
    </main>

    <LandingFooter
      :app-name="appName"
      :version="version"
    />
  </div>
</template>
