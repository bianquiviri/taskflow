<script setup>
import { useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: '',
    },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const generatedId = useId();
const selectId = props.id || `form-select-${generatedId}`;

function updateValue(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
  <div class="w-full">
    <label
      v-if="label"
      :for="selectId"
      class="mb-1.5 block text-sm font-medium text-content-muted"
    >
      {{ label }}
    </label>
    <select
      v-bind="$attrs"
      :id="selectId"
      :value="modelValue"
      :disabled="disabled"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${selectId}-error` : undefined"
      class="block w-full rounded-control border px-3 py-2 text-sm text-content shadow-sm transition-colors focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-sunken"
      :class="error
        ? 'border-danger focus:border-danger focus:ring-danger'
        : 'border-line-strong focus:border-focus focus:ring-focus'"
      @change="updateValue"
    >
      <slot />
    </select>
    <p
      v-if="error"
      :id="`${selectId}-error`"
      class="mt-1.5 text-sm text-danger-text"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
