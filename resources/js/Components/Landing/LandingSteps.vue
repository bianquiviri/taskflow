<script setup>
const steps = [
    {
        title: 'Create a project',
        description: 'Start from a name and a deadline, then invite the people who will work on it.',
    },
    {
        title: 'Add and assign tasks',
        description: 'Capture the work as tasks, give each one an owner and a priority.',
    },
    {
        title: 'Track it to done',
        description: 'Move tasks across the board and follow the numbers on your dashboard.',
    },
];
</script>

<template>
  <section class="border-t border-line py-16 sm:py-24">
    <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
      <h2 class="text-3xl font-bold tracking-tight text-content sm:text-4xl">
        How it works
      </h2>
      <p class="mt-4 max-w-2xl text-lg leading-relaxed text-content-muted">
        Three steps, whether you are a team of two or a hundred.
      </p>

      <ol class="mt-12 grid gap-8 sm:grid-cols-3">
        <li
          v-for="(step, index) in steps"
          :key="step.title"
        >
          <span class="flex size-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-content-inverted">
            {{ index + 1 }}
          </span>
          <h3 class="mt-4 text-base font-semibold text-content">
            {{ step.title }}
          </h3>
          <p class="mt-1.5 text-sm leading-relaxed text-content-muted">
            {{ step.description }}
          </p>
        </li>
      </ol>
    </div>
  </section>
</template>
