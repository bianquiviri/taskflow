<script setup>
import { computed } from 'vue';
import Icon from '../Icon.vue';

const props = defineProps({
    kpis: { type: Object, required: true },
});

const toneClasses = {
    brand: 'bg-brand-soft text-brand-text',
    info: 'bg-info-soft text-info-text',
    warning: 'bg-warning-soft text-warning-text',
    danger: 'bg-danger-soft text-danger-text',
    success: 'bg-success-soft text-success-text',
};

const cards = computed(() => {
    const metrics = props.kpis ?? {};
    const total = metrics.total ?? 0;
    const done = metrics.done ?? 0;
    const overdue = metrics.overdue ?? 0;
    const percent = total === 0 ? 0 : Math.round((100 * done) / total);

    return [
        {
            key: 'projects',
            label: 'Active projects',
            value: metrics.projects ?? 0,
            icon: 'projects',
            tone: 'brand',
        },
        {
            key: 'open',
            label: 'Open tasks',
            value: metrics.open ?? 0,
            icon: 'tasks',
            tone: 'info',
        },
        {
            key: 'overdue',
            label: 'Overdue tasks',
            value: overdue,
            icon: 'warning',
            tone: overdue > 0 ? 'danger' : 'warning',
            alert: overdue > 0,
        },
        {
            key: 'done',
            label: 'Completed',
            value: done,
            icon: 'check',
            tone: 'success',
            hint: total === 0 ? null : `${percent}% of all tasks`,
        },
    ];
});
</script>

<template>
  <ul
    data-test="kpi-cards"
    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
  >
    <li
      v-for="card in cards"
      :key="card.key"
      data-test="kpi-card"
      :data-card="card.key"
      :data-alert="card.alert ? 'true' : 'false'"
      class="rounded-panel border border-line bg-raised p-5 shadow-sm"
    >
      <div class="flex items-center gap-3">
        <span
          class="flex size-9 items-center justify-center rounded-control"
          :class="toneClasses[card.tone]"
        >
          <Icon
            :name="card.icon"
            class="size-5"
          />
        </span>
        <p
          data-test="kpi-card-label"
          class="text-sm font-medium text-content-subtle"
        >
          {{ card.label }}
        </p>
      </div>

      <p
        data-test="kpi-card-value"
        class="mt-4 text-3xl font-bold tracking-tight text-content"
      >
        {{ card.value }}
      </p>

      <p
        v-if="card.hint"
        data-test="kpi-card-hint"
        class="mt-1 text-xs text-content-subtle"
      >
        {{ card.hint }}
      </p>
    </li>
  </ul>
</template>
