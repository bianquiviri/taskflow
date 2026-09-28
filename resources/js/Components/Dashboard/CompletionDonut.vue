<script setup>
import { computed } from 'vue';

const props = defineProps({
    done: { type: Number, required: true },
    open: { type: Number, required: true },
});

const radius = 48;
const circumference = 2 * Math.PI * radius;

const total = computed(() => props.done + props.open);
const percent = computed(() => (total.value === 0 ? 0 : Math.round((100 * props.done) / total.value)));
const arc = computed(() => (circumference * props.done / total.value).toFixed(2));
const ring = circumference.toFixed(2);
</script>

<template>
  <section
    data-test="completion-donut"
    class="rounded-panel border border-line bg-raised p-5 shadow-sm"
  >
    <h2 class="text-sm font-semibold text-content">
      Progress
    </h2>

    <div class="mt-4 flex items-center gap-5">
      <div
        data-test="donut"
        class="relative size-32 shrink-0"
      >
        <svg
          viewBox="0 0 120 120"
          class="size-32 -rotate-90"
          aria-hidden="true"
        >
          <circle
            data-test="donut-track"
            cx="60"
            cy="60"
            :r="radius"
            fill="none"
            stroke-width="12"
            class="stroke-sunken"
          />
          <circle
            v-if="done > 0"
            data-test="donut-progress"
            cx="60"
            cy="60"
            :r="radius"
            fill="none"
            stroke-width="12"
            stroke-linecap="round"
            class="stroke-current text-success"
            :stroke-dasharray="`${arc} ${ring}`"
          />
        </svg>

        <p
          v-if="total > 0"
          data-test="donut-percent"
          class="absolute inset-0 flex items-center justify-center text-xl font-bold text-content"
        >
          {{ percent }}%
        </p>
        <p
          v-else
          data-test="donut-empty"
          class="absolute inset-0 flex items-center justify-center px-3 text-center text-xs text-content-subtle"
        >
          No tasks yet
        </p>
      </div>

      <ul
        data-test="donut-legend"
        class="space-y-2 text-sm"
      >
        <li
          data-legend="done"
          class="flex items-center gap-2 text-content"
        >
          <span
            class="size-2.5 rounded-full bg-success"
            aria-hidden="true"
          />
          Done
          <strong class="ml-auto">{{ done }}</strong>
        </li>
        <li
          data-legend="open"
          class="flex items-center gap-2 text-content"
        >
          <span
            class="size-2.5 rounded-full bg-info"
            aria-hidden="true"
          />
          Open
          <strong class="ml-auto">{{ open }}</strong>
        </li>
      </ul>
    </div>
  </section>
</template>
