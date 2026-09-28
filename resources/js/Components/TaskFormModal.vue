<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from './Button.vue';
import FormInput from './FormInput.vue';
import FormSelect from './FormSelect.vue';
import FormTextarea from './FormTextarea.vue';
import Modal from './Modal.vue';

const DEFAULT_PRIORITY = 'medium';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    projectId: { type: [Number, String], default: null },
    task: { type: Object, default: null },
    priorities: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const isEditing = computed(() => Boolean(props.task));
const dialogTitle = computed(() => (isEditing.value ? 'Edit task' : 'New task'));
const url = computed(() => (isEditing.value
    ? `/tasks/${props.task.id}`
    : `/projects/${props.projectId}/tasks`));

const form = useForm({
    title: '',
    description: '',
    priority: DEFAULT_PRIORITY,
    due_date: '',
});

watch(
    () => [props.modelValue, props.task],
    () => {
        if (props.modelValue) {
            fill();
        }
    },
    { immediate: true },
);

function fill() {
    form.title = props.task?.title ?? '';
    form.description = props.task?.description ?? '';
    form.priority = props.task?.priority ?? DEFAULT_PRIORITY;
    form.due_date = props.task?.due_date ? String(props.task.due_date).slice(0, 10) : '';
    form.clearErrors();
}

function submit() {
    form.transform((data) => ({
        title: data.title,
        description: data.description || null,
        priority: data.priority || DEFAULT_PRIORITY,
        due_date: data.due_date || null,
    }));

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:modelValue', false);
            form.reset();
        },
    };

    if (isEditing.value) {
        form.patch(url.value, options);

        return;
    }

    form.post(url.value, options);
}
</script>

<template>
  <Modal
    :model-value="modelValue"
    :title="dialogTitle"
    :description="isEditing ? 'Change what the task asks for.' : 'Add a task to the project board.'"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="form.title"
        name="title"
        label="Title"
        placeholder="What has to be done?"
        :error="form.errors.title"
        :disabled="form.processing"
      />

      <FormTextarea
        v-model="form.description"
        name="description"
        label="Description"
        rows="4"
        placeholder="Add the context the team needs."
        :error="form.errors.description"
        :disabled="form.processing"
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <FormSelect
          v-model="form.priority"
          name="priority"
          label="Priority"
          :error="form.errors.priority"
          :disabled="form.processing"
        >
          <option
            v-for="option in priorities"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </FormSelect>

        <FormInput
          v-model="form.due_date"
          name="due_date"
          type="date"
          label="Due date"
          :error="form.errors.due_date"
          :disabled="form.processing"
        />
      </div>

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
          {{ isEditing ? 'Save task' : 'Create task' }}
        </Button>
      </div>
    </form>
  </Modal>
</template>
