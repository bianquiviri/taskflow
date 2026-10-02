<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from '../../Components/Button.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageSkeleton from '../../Components/PageSkeleton.vue';
import ProjectFormModal from '../../Components/ProjectFormModal.vue';
import { useCan } from '../../Composables/useCan';
import { usePageLoading } from '../../Composables/usePageLoading';

defineProps({
    projects: { type: Array, required: true },
});

const { can } = useCan();
const { loading } = usePageLoading();

const formOpen = ref(false);
</script>

<template>
  <Head title="Projects" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-3xl font-bold tracking-tight text-content">
        Projects
      </h1>

      <Button
        v-if="can('projects.create') && projects.length > 0"
        data-test="project-create"
        @click="formOpen = true"
      >
        New project
      </Button>
    </div>

    <PageSkeleton
      v-if="loading"
      :rows="4"
    />

    <EmptyState
      v-else-if="projects.length === 0"
      data-test="projects-empty"
      class="mt-6"
      icon="projects"
      title="No projects yet"
      description="A project gathers the tasks of one piece of work. Create the first one to start tracking it."
    >
      <template
        v-if="can('projects.create')"
        #action
      >
        <Button
          data-test="project-create-empty"
          @click="formOpen = true"
        >
          New project
        </Button>
      </template>
    </EmptyState>

    <ul
      v-else
      class="mt-6 space-y-4"
    >
      <li
        v-for="project in projects"
        :key="project.id"
        data-test="project-row"
        class="rounded-panel border border-line bg-raised p-5 shadow-sm"
      >
        <h2 class="text-lg font-semibold text-content">
          {{ project.name }}
        </h2>
        <p
          v-if="project.description"
          data-test="project-description"
          class="mt-1 text-sm text-content-subtle"
        >
          {{ project.description }}
        </p>
      </li>
    </ul>
  </div>

  <ProjectFormModal v-model="formOpen" />
</template>
