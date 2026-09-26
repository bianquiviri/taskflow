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
    type: { type: String, default: 'text' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const generatedId = useId();
const inputId = props.id || `form-input-${generatedId}`;

function updateValue(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
  <div class="w-full">
    <label
      v-if="label"
      :for="inputId"
      class="mb-1.5 block text-sm font-medium text-content-muted"
    >
      {{ label }}
    </label>
    <input
      v-bind="$attrs"
      :id="inputId"
      :type="type"
      :value="modelValue"
      :disabled="disabled"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${inputId}-error` : undefined"
      class="block w-full rounded-control border px-3 py-2 text-sm text-content shadow-sm transition-colors placeholder:text-content-faint focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-sunken"
      :class="error
        ? 'border-danger focus:border-danger focus:ring-danger'
        : 'border-line-strong focus:border-focus focus:ring-focus'"
      @input="updateValue"
    >
    <p
      v-if="error"
      :id="`${inputId}-error`"
      class="mt-1.5 text-sm text-danger-text"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
