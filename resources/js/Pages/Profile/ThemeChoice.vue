<script setup>
import { computed, useId } from 'vue';
import { resolveTheme } from '../../Composables/useTheme';
import { persistTheme } from '../../Composables/useThemePreference';

const props = defineProps({
    modelValue: {
        type: String,
        default: 'light',
    },
});

const emit = defineEmits(['update:modelValue']);

const themes = [
    { value: 'light', label: 'Light', hint: 'Bright surfaces for daytime' },
    { value: 'dark', label: 'Dark', hint: 'Dimmed surfaces for low light' },
];

const name = useId();
const selected = computed(() => resolveTheme(props.modelValue));

function isSelected(theme) {
    return selected.value === theme;
}

function select(theme) {
    if (isSelected(theme)) {
        return;
    }

    emit('update:modelValue', theme);

    persistTheme(theme);
}
</script>

<template>
  <fieldset>
    <legend class="text-sm font-medium text-content-muted">
      Theme
    </legend>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
      <label
        v-for="theme in themes"
        :key="theme.value"
        class="flex cursor-pointer items-start gap-3 rounded-panel border p-3 transition-colors"
        :class="isSelected(theme.value)
          ? 'border-brand-600 bg-brand-soft'
          : 'border-line bg-raised hover:bg-sunken'"
      >
        <input
          class="mt-0.5 size-4 shrink-0 border-line-strong text-brand-600 dark:text-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-raised"
          type="radio"
          :name="`theme-${name}`"
          :value="theme.value"
          :checked="isSelected(theme.value)"
          @change="select(theme.value)"
        >
        <span>
          <span class="block text-sm font-semibold text-content">{{ theme.label }}</span>
          <span class="block text-xs text-content-subtle">{{ theme.hint }}</span>
        </span>
      </label>
    </div>
  </fieldset>
</template>
