<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    closeLabel: { type: String, default: 'Close dialog' },
});

const emit = defineEmits(['update:modelValue', 'close']);
const generatedId = useId();
const titleId = `modal-title-${generatedId}`;
const descriptionId = `modal-description-${generatedId}`;
const dialog = ref(null);
let previouslyFocusedElement = null;

const focusableSelector = [
    'a[href]:not([tabindex="-1"])',
    'button:not([disabled]):not([tabindex="-1"])',
    'input:not([disabled]):not([type="hidden"]):not([tabindex="-1"])',
    'select:not([disabled]):not([tabindex="-1"])',
    'textarea:not([disabled]):not([tabindex="-1"])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

function getFocusableElements() {
    return Array.from(dialog.value?.querySelectorAll(focusableSelector) || []);
}

function focusDialog() {
    const target = getFocusableElements()[0] || dialog.value;
    target?.focus();
}

function activateFocusTrap() {
    if (previouslyFocusedElement) {
        return;
    }

    previouslyFocusedElement = document.activeElement;
    document.addEventListener('keydown', handleKeydown);
    nextTick(focusDialog);
}

function deactivateFocusTrap() {
    document.removeEventListener('keydown', handleKeydown);

    if (previouslyFocusedElement?.isConnected && typeof previouslyFocusedElement.focus === 'function') {
        previouslyFocusedElement.focus();
    }

    previouslyFocusedElement = null;
}

function requestClose() {
    emit('update:modelValue', false);
    emit('close');
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        requestClose();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusableElements = getFocusableElements();
    if (focusableElements.length === 0) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    const activeElement = document.activeElement;
    const focusIsOutside = !focusableElements.includes(activeElement) && activeElement !== dialog.value;

    if (focusIsOutside) {
        event.preventDefault();
        const target = event.shiftKey ? lastElement : firstElement;
        target.focus();
    } else if (event.shiftKey && (activeElement === firstElement || activeElement === dialog.value)) {
        event.preventDefault();
        lastElement.focus();
    } else if (!event.shiftKey && activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            activateFocusTrap();
        } else {
            deactivateFocusTrap();
        }
    },
    { immediate: true },
);

onBeforeUnmount(deactivateFocusTrap);
</script>

<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      data-test="modal-overlay"
      class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/60 p-4 backdrop-blur-sm"
      @click.self="requestClose"
    >
      <div
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :aria-describedby="description ? descriptionId : undefined"
        tabindex="-1"
        class="my-auto w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-xl outline-none dark:border-gray-700 dark:bg-gray-900"
      >
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <div class="min-w-0 flex-1">
            <h2
              :id="titleId"
              class="text-lg font-semibold text-gray-900 dark:text-white"
            >
              {{ title }}
            </h2>
            <p
              v-if="description"
              :id="descriptionId"
              data-test="modal-description"
              class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
              {{ description }}
            </p>
          </div>
          <button
            type="button"
            :aria-label="closeLabel"
            class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
            @click="requestClose"
          >
            <Icon
              name="close"
              class="size-5"
            />
          </button>
        </div>

        <div class="overflow-y-auto px-6 py-4">
          <slot />
        </div>

        <div
          v-if="$slots.footer"
          class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700"
        >
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
