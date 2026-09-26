<script setup>
defineProps({
    tone: {
        type: String,
        default: 'indigo',
        validator: (value) => ['indigo', 'sky', 'gray', 'green', 'amber', 'red'].includes(value),
    },
});

const toneClasses = {
    indigo: 'bg-brand-soft text-brand-text ring-brand-600/20',
    sky: 'bg-info-soft text-info-text ring-info/20',
    gray: 'bg-sunken text-content-muted ring-line-strong',
    green: 'bg-success-soft text-success-text ring-success/20',
    amber: 'bg-warning-soft text-warning-text ring-warning/20',
    red: 'bg-danger-soft text-danger-text ring-danger/20',
};
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
    :class="toneClasses[tone]"
  >
    <slot />
  </span>
</template>
