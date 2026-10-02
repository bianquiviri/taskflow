<script setup>
import Skeleton from '../Skeleton.vue';

const KPI_PLACEHOLDERS = 4;
const CHART_PLACEHOLDERS = 3;
const LIST_PLACEHOLDERS = 2;
</script>

<template>
  <div
    data-test="dashboard-skeleton"
    role="status"
    aria-busy="true"
    class="mt-6 space-y-6"
  >
    <span class="sr-only">Loading…</span>

    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <li
        v-for="card in KPI_PLACEHOLDERS"
        :key="card"
        data-test="dashboard-skeleton-kpi"
        class="rounded-panel border border-line bg-raised p-5 shadow-sm"
      >
        <Skeleton class="h-4 w-24" />
        <Skeleton class="mt-4 h-8 w-16" />
      </li>
    </ul>

    <div class="grid gap-6 lg:grid-cols-3">
      <div
        v-for="panel in CHART_PLACEHOLDERS"
        :key="panel"
        data-test="dashboard-skeleton-panel"
        class="rounded-panel border border-line bg-raised p-5 shadow-sm"
      >
        <Skeleton class="h-4 w-32" />
        <Skeleton class="mt-4 h-32 w-full" />
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <div
        v-for="panel in LIST_PLACEHOLDERS"
        :key="`list-${panel}`"
        data-test="dashboard-skeleton-panel"
        class="rounded-panel border border-line bg-raised p-5 shadow-sm"
      >
        <Skeleton class="h-4 w-40" />
        <Skeleton class="mt-4 h-24 w-full" />
      </div>
    </div>
  </div>
</template>