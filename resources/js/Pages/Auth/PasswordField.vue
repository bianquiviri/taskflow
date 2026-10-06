<script setup>
import { computed, ref } from 'vue';
import FormInput from '../../Components/FormInput.vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: '',
    },
    name: { type: String, required: true },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    minlength: { type: [String, Number], default: undefined },
    autocomplete: { type: String, default: 'current-password' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: true },
    autofocus: { type: Boolean, default: false },
    revealLabel: { type: String, default: 'Show password' },
    hideLabel: { type: String, default: 'Hide password' },
});

const emit = defineEmits(['update:modelValue']);

const revealed = ref(false);
const type = computed(() => (revealed.value ? 'text' : 'password'));
const toggleLabel = computed(() => (revealed.value ? props.hideLabel : props.revealLabel));
</script>

<template>
  <div class="w-full">
    <FormInput
      :model-value="modelValue"
      :name="name"
      :type="type"
      :label="label"
      :hint="hint"
      :minlength="minlength"
      :autocomplete="autocomplete"
      :error="error"
      :disabled="disabled"
      :required="required"
      :autofocus="autofocus"
      @update:model-value="emit('update:modelValue', $event)"
    />

    <div class="mt-1.5 flex justify-end">
      <button
        data-test="password-toggle"
        type="button"
        :disabled="disabled"
        :aria-pressed="revealed"
        class="text-xs font-semibold text-brand-text transition-colors hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-raised disabled:cursor-not-allowed disabled:opacity-60"
        @click="revealed = !revealed"
      >
        {{ toggleLabel }}
      </button>
    </div>
  </div>
</template>
