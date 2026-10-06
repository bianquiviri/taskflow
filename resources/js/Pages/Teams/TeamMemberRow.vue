<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Avatar from '../../Components/Avatar.vue';
import Badge from '../../Components/Badge.vue';
import Button from '../../Components/Button.vue';
import FormSelect from '../../Components/FormSelect.vue';

const props = defineProps({
    team: { type: Object, required: true },
    member: { type: Object, required: true },
    roles: { type: Array, required: true },
    canManageMembers: { type: Boolean, default: false },
});

const form = useForm({ role: props.member.role });

const currentRole = computed(() => props.roles.find((role) => role.value === props.member.role) ?? null);

const isDirty = computed(() => form.role !== props.member.role);

watch(
    () => props.member.role,
    (role) => {
        form.role = role;
    },
);

function saveRole() {
    form.patch(`/teams/${props.team.id}/members/${props.member.id}`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function removeMember() {
    form.delete(`/teams/${props.team.id}/members/${props.member.id}`, { preserveScroll: true });
}
</script>

<template>
  <li
    data-test="member-row"
    class="flex flex-wrap items-center gap-3 rounded-panel border border-line bg-raised px-4 py-3"
  >
    <Avatar
      size="sm"
      :name="member.name"
    />
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-content">
        {{ member.name }}
      </p>
      <p class="truncate text-xs text-content-muted">
        {{ member.email }}
      </p>
    </div>

    <Badge
      v-if="currentRole"
      :tone="currentRole.tone"
    >
      {{ currentRole.label }}
    </Badge>

    <form
      v-if="member.canBeReassigned && canManageMembers"
      class="flex items-end gap-2"
      @submit.prevent="saveRole"
    >
      <FormSelect
        v-model="form.role"
        class="w-36"
        name="role"
        :aria-label="`Role for ${member.name}`"
        :error="form.errors.role"
        :disabled="form.processing"
      >
        <option
          v-for="role in roles"
          :key="role.value"
          :value="role.value"
        >
          {{ role.label }}
        </option>
      </FormSelect>
      <Button
        type="submit"
        variant="secondary"
        :disabled="!isDirty"
        :loading="form.processing"
      >
        Save
      </Button>
    </form>

    <Button
      v-if="member.canBeRemoved && canManageMembers"
      data-test="remove-member"
      variant="ghost"
      :disabled="form.processing"
      @click="removeMember"
    >
      Remove
    </Button>
  </li>
</template>
