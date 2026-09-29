<script setup>
import { computed } from 'vue';

const props = defineProps({
    series: { type: Array, required: true },
});

const toneClasses = {
    todo: 'bg-content-faint',
    in_progress: 'bg-info',
    in_review: 'bg-warning',
    done: 'bg-success',
    cancelled: 'bg-danger',
};

const total = computed(() => props.series.reduce((sum, row) => sum + (row.count ?? 0), 0));
const busiest = computed(() => Math.max(1, ...props.series.map((row) => row.count ?? 0)));

const rows = computed(() => props.series.map((row) => {
    const count = row.count ?? 0;

    return {
        ...row,
        count,
        width: (100 * count) / busiest.value,
        share: total.value === 0 ? 0 : Math.round((100 * count) / total.value),
    };
}));
</script>

<template>
  <section
    data-test="status-chart"
    class="rounded-panel border border-line bg-raised p-5 shadow-sm"
  >
    <h2 class="text-sm font-semibold text-content">
      Tasks by status
    </h2>

    <p
      v-if="total === 0"
      data-test="status-chart-empty"
      class="mt-6 text-sm text-content-subtle"
    >
      No tasks yet
    </p>

    <template v-else>
      <ul class="mt-4 space-y-4">
        <li
          v-for="row in rows"
          :key="row.value"
          data-test="status-row"
          :data-status="row.value"
        >
          <div class="flex items-baseline justify-between gap-3 text-sm">
            <span
              data-test="status-row-label"
              class="font-medium text-content"
            >{{ row.label }}</span>
            <span class="text-content-subtle">
              <span data-test="status-row-count">{{ row.count }}</span>
              ·
              <span data-test="status-row-share">{{ row.share }}%</span>
            </span>
          </div>

          <div
            class="mt-1.5 h-2 overflow-hidden rounded-full bg-sunken"
            aria-hidden="true"
          >
            <div
              data-test="status-row-bar"
              class="h-full rounded-full"
              :class="toneClasses[row.value] ?? toneClasses.todo"
              :style="{ width: `${row.width}%` }"
            />
          </div>
        </li>
      </ul>

      <p
        data-test="status-chart-total"
        class="mt-5 border-t border-line pt-3 text-xs text-content-subtle"
      >
        {{ total }} {{ total === 1 ? 'task' : 'tasks' }} in your projects
      </p>
    </template>
  </section>
</template>
