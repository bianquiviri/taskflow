<script setup>
import { computed } from 'vue';
import Badge from './Badge.vue';

const props = defineProps({
    value: { type: String, required: true },
});

const valueConfig = {
    todo: { label: 'Todo', tone: 'gray' },
    in_progress: { label: 'In progress', tone: 'sky' },
    in_review: { label: 'In review', tone: 'amber' },
    done: { label: 'Done', tone: 'green' },
    cancelled: { label: 'Cancelled', tone: 'red' },
    low: { label: 'Low', tone: 'gray' },
    medium: { label: 'Medium', tone: 'indigo' },
    high: { label: 'High', tone: 'amber' },
    urgent: { label: 'Urgent', tone: 'red' },
};

const normalizedValue = computed(() => props.value.trim().toLowerCase());
const configuration = computed(() => valueConfig[normalizedValue.value] || {
    label: humanize(normalizedValue.value),
    tone: 'gray',
});

function humanize(value) {
    const label = value.replace(/[_-]+/g, ' ').trim();
    return label ? `${label.charAt(0).toUpperCase()}${label.slice(1)}` : 'Unknown';
}
</script>

<template>
  <Badge :tone="configuration.tone">
    {{ configuration.label }}
  </Badge>
</template>
