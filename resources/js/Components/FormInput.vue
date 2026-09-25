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
      class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200"
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
      class="block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors placeholder:text-gray-400 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800"
      :class="error
        ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500'
        : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'"
      @input="updateValue"
    >
    <p
      v-if="error"
      :id="`${inputId}-error`"
      class="mt-1.5 text-sm text-rose-600 dark:text-rose-400"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
