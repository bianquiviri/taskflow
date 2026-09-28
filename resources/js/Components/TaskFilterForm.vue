<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from './Button.vue';
import FormInput from './FormInput.vue';
import FormSelect from './FormSelect.vue';

const props = defineProps({
    url: { type: String, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    assignees: { type: Array, default: () => [] },
});

const fields = ['search', 'status', 'priority', 'assignee_id', 'due_from', 'due_to'];

const form = ref(pickFilters());
const hasFilters = computed(() => fields.some((field) => form.value[field] !== ''));

watch(() => props.filters, () => {
    form.value = pickFilters();
});

function apply() {
    router.get(props.url, params(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear() {
    form.value = emptyFilters();

    apply();
}

function params() {
    return fields.reduce((query, field) => {
        const value = form.value[field];

        if (value !== '' && value !== null && value !== undefined) {
            query[field] = value;
        }

        return query;
    }, {});
}

function emptyFilters() {
    return fields.reduce((filters, field) => ({ ...filters, [field]: '' }), {});
}

function pickFilters() {
    return fields.reduce((filters, field) => ({
        ...filters,
        [field]: props.filters?.[field] ?? '',
    }), {});
}
</script>

<template>
  <form
    data-test="task-filter-form"
    class="rounded-panel border border-line bg-raised p-4"
    @submit.prevent="apply"
  >
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <FormInput
        v-model="form.search"
        label="Search"
        type="search"
        placeholder="Search title and description"
        data-test="filter-search"
      />

      <FormSelect
        v-model="form.status"
        label="Status"
        data-test="filter-status"
      >
        <option value="">
          Any status
        </option>
        <option
          v-for="status in statuses"
          :key="status.value"
          :value="status.value"
        >
          {{ status.label }}
        </option>
      </FormSelect>

      <FormSelect
        v-model="form.priority"
        label="Priority"
        data-test="filter-priority"
      >
        <option value="">
          Any priority
        </option>
        <option
          v-for="priority in priorities"
          :key="priority.value"
          :value="priority.value"
        >
          {{ priority.label }}
        </option>
      </FormSelect>

      <FormSelect
        v-if="assignees.length > 0"
        v-model="form.assignee_id"
        label="Assignee"
        data-test="filter-assignee"
      >
        <option value="">
          Anyone
        </option>
        <option
          v-for="assignee in assignees"
          :key="assignee.id"
          :value="String(assignee.id)"
        >
          {{ assignee.name }}
        </option>
      </FormSelect>

      <FormInput
        v-model="form.due_from"
        label="Due from"
        type="date"
        data-test="filter-due-from"
      />

      <FormInput
        v-model="form.due_to"
        label="Due to"
        type="date"
        data-test="filter-due-to"
      />
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <Button
        type="submit"
        data-test="filter-apply"
      >
        Apply filters
      </Button>
      <Button
        v-if="hasFilters"
        variant="ghost"
        data-test="filter-clear"
        @click="clear"
      >
        Clear
      </Button>
    </div>
  </form>
</template>
