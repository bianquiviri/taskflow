<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CompletionDonut from '../../Components/Dashboard/CompletionDonut.vue';
import DashboardSkeleton from '../../Components/Dashboard/DashboardSkeleton.vue';
import KpiCards from '../../Components/Dashboard/KpiCards.vue';
import OpenTaskList from '../../Components/Dashboard/OpenTaskList.vue';
import QuickActions from '../../Components/Dashboard/QuickActions.vue';
import RecentProjects from '../../Components/Dashboard/RecentProjects.vue';
import StatusBreakdownChart from '../../Components/Dashboard/StatusBreakdownChart.vue';
import Checklist from '../../Components/Onboarding/Checklist.vue';
import { usePageLoading } from '../../Composables/usePageLoading';

const props = defineProps({
    kpis: { type: Object, required: true },
    statusBreakdown: { type: Array, required: true },
    recentProjects: { type: Array, required: true },
    openTasks: { type: Array, required: true },
});

const page = usePage();
const { loading } = usePageLoading();

const team = computed(() => page.props.auth?.team ?? null);
const hasProject = computed(() => (props.kpis.projects ?? 0) > 0);
</script>

<template>
  <Head title="Dashboard" />

  <div class="mx-auto w-full max-w-6xl px-6 py-10">
    <header>
      <h1 class="text-3xl font-bold tracking-tight text-content">
        Dashboard
      </h1>
      <p class="mt-2 text-sm text-content-subtle">
        Your projects and the work waiting on you, at a glance.
      </p>
    </header>

    <DashboardSkeleton v-if="loading" />

    <template v-else>
      <div
        v-if="!hasProject"
        class="mt-6"
      >
        <Checklist
          :has-team="team !== null"
          :has-project="hasProject"
          :has-task="(kpis.total ?? 0) > 0"
          :team-id="team?.id ?? null"
          :project-id="recentProjects[0]?.id ?? null"
        />
      </div>

      <div class="mt-6">
        <KpiCards :kpis="kpis" />
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <StatusBreakdownChart :series="statusBreakdown" />
        <CompletionDonut
          :done="kpis.done"
          :open="kpis.open"
        />
        <QuickActions />
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <RecentProjects :projects="recentProjects" />
        <OpenTaskList :tasks="openTasks" />
      </div>
    </template>
  </div>
</template>
