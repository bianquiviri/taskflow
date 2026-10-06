<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Badge from '../../Components/Badge.vue';
import Button from '../../Components/Button.vue';
import EmptyState from '../../Components/EmptyState.vue';
import FormInput from '../../Components/FormInput.vue';
import FormSelect from '../../Components/FormSelect.vue';
import TeamInvitationRow from './TeamInvitationRow.vue';
import TeamMemberRow from './TeamMemberRow.vue';

const props = defineProps({
    team: { type: Object, required: true },
    members: { type: Array, required: true },
    invitations: { type: Array, required: true },
    roles: { type: Array, required: true },
    assignableRoles: { type: Array, required: true },
    canManageMembers: { type: Boolean, default: false },
});

const form = useForm({
    email: '',
    role: 'member',
});

const pendingCount = computed(
    () => props.invitations.filter((invitation) => invitation.status === 'pending').length,
);

function invite() {
    form.post(`/teams/${props.team.id}/invitations`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
  <Head :title="`${team.name} settings`" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-content">
          {{ team.name }} settings
        </h1>
        <p class="mt-1 text-sm text-content-muted">
          Manage who belongs to this team and what they are allowed to do.
        </p>
      </div>
      <Link
        data-test="back-to-team"
        :href="`/teams/${team.id}`"
        class="text-sm font-medium text-brand-text hover:underline"
      >
        Back to the team
      </Link>
    </div>

    <section
      v-if="canManageMembers"
      data-test="invite-form"
      class="mt-8 rounded-panel border border-line bg-raised p-5 shadow-sm"
    >
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-muted">
        Invite somebody
      </h2>

      <form
        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start"
        @submit.prevent="invite"
      >
        <FormInput
          v-model="form.email"
          class="flex-1"
          name="email"
          type="email"
          label="Email address"
          placeholder="teammate@example.com"
          :error="form.errors.email"
          :disabled="form.processing"
        />
        <FormSelect
          v-model="form.role"
          class="sm:w-40"
          name="role"
          label="Role"
          :error="form.errors.role"
          :disabled="form.processing"
        >
          <option
            v-for="role in assignableRoles"
            :key="role.value"
            :value="role.value"
          >
            {{ role.label }}
          </option>
        </FormSelect>
        <Button
          class="mt-7"
          type="submit"
          :loading="form.processing"
        >
          Send invitation
        </Button>
      </form>
    </section>

    <section class="mt-10">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-content-muted">
          Members
        </h2>
        <Badge tone="gray">
          {{ members.length }}
        </Badge>
      </div>

      <EmptyState
        v-if="members.length === 0"
        data-test="members-empty"
        class="mt-4"
        icon="user"
        title="No members yet"
        description="Invite somebody to start collaborating."
      />

      <ul
        v-else
        class="mt-4 space-y-3"
      >
        <TeamMemberRow
          v-for="member in members"
          :key="member.id"
          :team="team"
          :member="member"
          :roles="assignableRoles"
          :can-manage-members="canManageMembers"
        />
      </ul>
    </section>

    <section class="mt-10">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-content-muted">
          Invitations
        </h2>
        <Badge
          v-if="pendingCount > 0"
          tone="amber"
        >
          {{ pendingCount }} pending
        </Badge>
      </div>

      <EmptyState
        v-if="invitations.length === 0"
        data-test="invitations-empty"
        class="mt-4"
        icon="bell"
        title="No invitations yet"
        description="Invitations you send from here show up until they are accepted or cancelled."
      />

      <ul
        v-else
        class="mt-4 space-y-3"
      >
        <TeamInvitationRow
          v-for="invitation in invitations"
          :key="invitation.id"
          :team="team"
          :invitation="invitation"
          :roles="roles"
          :can-manage-members="canManageMembers"
        />
      </ul>
    </section>
  </div>
</template>
