<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from './Button.vue';
import FormInput from './FormInput.vue';
import FormTextarea from './FormTextarea.vue';
import Modal from './Modal.vue';

defineProps({
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const form = useForm({
    name: '',
    description: '',
});

function submit() {
    form.transform((data) => ({
        name: data.name,
        description: data.description || null,
    }));

    form.post('/projects', {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:modelValue', false);
            form.reset();
        },
    });
}
</script>

<template>
  <Modal
    :model-value="modelValue"
    title="New project"
    description="A project gathers the tasks of one piece of work."
    @update:model-value="emit('update:modelValue', $event)"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="form.name"
        name="name"
        label="Name"
        placeholder="Website redesign"
        :error="form.errors.name"
        :disabled="form.processing"
      />

      <FormTextarea
        v-model="form.description"
        name="description"
        label="Description"
        rows="4"
        placeholder="What is this project about?"
        :error="form.errors.description"
        :disabled="form.processing"
      />

      <div class="flex items-center justify-end gap-3 pt-2">
        <Button
          variant="secondary"
          :disabled="form.processing"
          @click="emit('update:modelValue', false)"
        >
          Cancel
        </Button>
        <Button
          type="submit"
          :loading="form.processing"
        >
          Create project
        </Button>
      </div>
    </form>
  </Modal>
</template>