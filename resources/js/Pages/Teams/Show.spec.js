import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        email: '',
        role: 'member',
        processing: false,
        errors: {},
        post: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<div />',
    },
    useForm: (initial) => {
        Object.assign(formState, initial);
        return formState;
    },
}));

import Show from './Show.vue';

const team = { id: 7, name: 'Platform', slug: 'platform' };

const roles = [
    { value: 'owner', label: 'Owner', tone: 'indigo' },
    { value: 'admin', label: 'Admin', tone: 'sky' },
    { value: 'member', label: 'Member', tone: 'gray' },
];

const invitableRoles = roles.filter((role) => role.value !== 'owner');

const owner = { id: 1, name: 'Ada Lovelace', email: 'ada@example.com', role: 'owner', canBeRemoved: false };
const member = { id: 2, name: 'Grace Hopper', email: 'grace@example.com', role: 'member', canBeRemoved: true };
const invitation = {
    id: 30,
    email: 'invitee@example.com',
    role: 'admin',
    expiresAt: '2026-10-07T10:00:00+00:00',
};

const mountPage = (props = {}) =>
    mount(Show, {
        props: {
            team,
            members: [owner, member],
            pendingInvitations: [invitation],
            roles,
            invitableRoles,
            canManageMembers: true,
            ...props,
        },
    });

beforeEach(() => {
    Object.assign(formState, {
        email: '',
        role: 'member',
        processing: false,
        errors: {},
    });
    formState.post.mockReset();
    formState.delete.mockReset();
    formState.reset.mockReset();
});

describe('Teams/Show.vue', () => {
    it('renders the team name with its members and roles', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Platform');
        expect(wrapper.text()).toContain('Ada Lovelace');
        expect(wrapper.text()).toContain('ada@example.com');
        expect(wrapper.text()).toContain('Grace Hopper');
        expect(wrapper.text()).toContain('Admin');
    });

    it('lists the pending invitations with their expiry', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-test="invitation-row"]').text()).toContain('invitee@example.com');
        expect(wrapper.get('[data-test="invitation-row"]').text()).toContain('7 Oct 2026');
        expect(wrapper.get('[data-test="invitation-row"]').text()).toContain('Admin');
    });

    it('omits the role badge when the role is unknown', () => {
        const wrapper = mountPage({ roles: [] });

        expect(wrapper.get('[data-test="member-row"]').text()).not.toContain('Owner');
        expect(wrapper.get('[data-test="member-row"]').text()).toContain('Ada Lovelace');
    });

    it('shows empty states when the team has no members nor invitations', () => {
        const wrapper = mountPage({ members: [], pendingInvitations: [] });

        expect(wrapper.get('[data-test="members-empty"]').text()).toContain('No members yet');
        expect(wrapper.get('[data-test="invitations-empty"]').text()).toContain('No pending invitations');
    });

    it('offers the invitable roles in the invite form', () => {
        const wrapper = mountPage();

        const options = wrapper.findAll('select option').map((option) => option.text());

        expect(wrapper.get('[data-test="invite-form"]').exists()).toBe(true);
        expect(options).toEqual(['Admin', 'Member']);
    });

    it('hides the invite form from members that cannot manage members', () => {
        const wrapper = mountPage({
            canManageMembers: false,
            members: [{ ...owner, canBeRemoved: false }, { ...member, canBeRemoved: false }],
        });

        expect(wrapper.find('[data-test="invite-form"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="member-row"] button')).toHaveLength(0);
    });

    it('posts the invitation to the team endpoint', async () => {
        const wrapper = mountPage();

        await wrapper.get('input[name="email"]').setValue('invitee@example.com');
        await wrapper.get('form').trigger('submit');

        expect(formState.email).toBe('invitee@example.com');
        expect(formState.post).toHaveBeenCalledWith('/teams/7/invitations', expect.any(Object));
    });

    it('clears the invite form once the invitation is sent', async () => {
        const wrapper = mountPage();

        await wrapper.get('form').trigger('submit');
        const [, options] = formState.post.mock.calls[0];
        options.onSuccess();

        expect(options.preserveScroll).toBe(true);
        expect(formState.reset).toHaveBeenCalled();
    });

    it('displays the invite validation errors', () => {
        formState.errors = { email: 'An invitation for this address is already pending.' };
        const wrapper = mountPage();

        expect(wrapper.get('[role="alert"]').text()).toBe('An invitation for this address is already pending.');
    });

    it('revokes a pending invitation', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="invitation-row"] button').trigger('click');

        expect(formState.delete).toHaveBeenCalledWith('/teams/7/invitations/30', expect.any(Object));
    });

    it('removes a member but never the owner', async () => {
        const wrapper = mountPage();

        const rows = wrapper.findAll('[data-test="member-row"]');
        expect(rows[0].text()).not.toContain('Remove');
        expect(rows[1].text()).toContain('Remove');

        await rows[1].get('button').trigger('click');

        expect(formState.delete).toHaveBeenCalledWith('/teams/7/members/2', expect.any(Object));
    });
});
