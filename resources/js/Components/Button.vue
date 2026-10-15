<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'danger', 'ghost'].includes(value),
    },
    type: {
        type: String,
        default: 'button',
        validator: (value) => ['button', 'submit', 'reset'].includes(value),
    },
    href: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const variantClasses = {
    primary: 'bg-brand-600 text-content-inverted shadow-sm hover:bg-brand-700 focus-visible:ring-focus',
    secondary: 'border border-line-strong bg-raised text-content-muted shadow-sm hover:bg-sunken focus-visible:ring-focus',
    danger: 'bg-danger text-content-inverted shadow-sm hover:brightness-95 focus-visible:ring-danger',
    ghost: 'text-content-muted hover:bg-sunken focus-visible:ring-focus',
};

const isDisabled = computed(() => props.disabled || props.loading);

// A plain in-page anchor must not go through Inertia's router.
const tag = computed(() => {
    if (!props.href) {
        return 'button';
    }

    return props.href.startsWith('#') ? 'a' : Link;
});
</script>

<template>
  <component
    :is="tag"
    :href="href ?? undefined"
    :type="href ? undefined : type"
    :disabled="href ? undefined : isDisabled"
    :aria-busy="loading || undefined"
    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-control px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-60"
    :class="variantClasses[variant]"
  >
    <span
      v-if="loading"
      data-test="button-spinner"
      class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"
      aria-hidden="true"
    />
    <slot />
  </component>
</template>
