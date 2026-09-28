import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        email: '',
        role: 'member',
        processing: false,
        errors: {},
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (initial) => {
        Object.assign(formState, initial);
        return formState;
    },
}));

import Settings from './Settings.vue';

const team = { id: 7, name: 'Platform', slug: 'platform' };

const roles = [
    { value: 'owner', label: 'Owner', tone: 'indigo' },
    { value: 'admin', label: 'Admin', tone: 'sky' },
    { value: 'member', label: 'Member', tone: 'gray' },
];

const assignableRoles = roles.filter((role) => role.value !== 'owner');

const owner = { id: 1, name: 'Ada Lovelace', email: 'ada@example.com', role: 'owner', canBeRemoved: false, canBeReassigned: false };
const member = { id: 2, name: 'Grace Hopper', email: 'grace@example.com', role: 'member', canBeRemoved: true, canBeReassigned: true };
const invitation = {
    id: 30,
    email: 'invitee@example.com',
    role: 'admin',
    status: 'pending',
    expiresAt: '2026-10-07T10:00:00+00:00',
    canBeResent: true,
    canBeCancelled: true,
};

const mountPage = (props = {}) =>
    mount(Settings, {
        props: {
            team,
            members: [owner, member],
            invitations: [invitation],
            roles,
            assignableRoles,
            canManageMembers: true,
            ...props,
        },
    });

beforeEach(() => {
    Object.assign(formState, { email: '', role: 'member', processing: false, errors: {} });
    formState.post.mockReset();
    formState.patch.mockReset();
    formState.delete.mockReset();
    formState.reset.mockReset();
});

describe('Teams/Settings.vue', () => {
    it('renders the team settings with its members and invitations', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Platform');
        expect(wrapper.text()).toContain('Ada Lovelace');
        expect(wrapper.text()).toContain('Grace Hopper');
        expect(wrapper.get('[data-test="invitation-row"]').text()).toContain('invitee@example.com');
    });

    it('points back to the team overview', () => {
        expect(mountPage().get('[data-test="back-to-team"]').attributes('href')).toBe('/teams/7');
    });

    it('offers the assignable roles to a manager', () => {
        const wrapper = mountPage();

        const options = wrapper.get('[data-test="member-row"] select').findAll('option').map((option) => option.text());

        expect(options).toEqual(['Admin', 'Member']);
    });

    it('never offers the owner role as an assignable one', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-test="invite-form"] select option').text()).not.toBe('Owner');
    });

    it('shows empty states when the team has no members nor invitations', () => {
        const wrapper = mountPage({ members: [], invitations: [] });

        expect(wrapper.get('[data-test="members-empty"]').text()).toContain('No members yet');
        expect(wrapper.get('[data-test="invitations-empty"]').text()).toContain('No invitations yet');
    });

    it('hides every management control from viewers that cannot manage members', () => {
        const wrapper = mountPage({
            canManageMembers: false,
            members: [{ ...owner }, { ...member, canBeRemoved: false, canBeReassigned: false }],
            invitations: [{ ...invitation, canBeResent: false, canBeCancelled: false }],
        });

        expect(wrapper.find('[data-test="invite-form"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="member-row"] form')).toHaveLength(0);
        expect(wrapper.findAll('[data-test="member-row"] button')).toHaveLength(0);
        expect(wrapper.find('[data-test="resend-invitation"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="cancel-invitation"]').exists()).toBe(false);
    });

    it('sends an invitation through the shared team endpoint', async () => {
        const wrapper = mountPage();

        await wrapper.get('input[name="email"]').setValue('new@example.com');
        await wrapper.get('[data-test="invite-form"] form').trigger('submit');

        expect(formState.post).toHaveBeenCalledWith('/teams/7/invitations', expect.any(Object));
    });

    it('clears the invite form once the invitation is sent', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="invite-form"] form').trigger('submit');
        const [, options] = formState.post.mock.calls[0];
        options.onSuccess();

        expect(options.preserveScroll).toBe(true);
        expect(formState.reset).toHaveBeenCalled();
    });

    it('displays the invite validation errors', () => {
        formState.errors = { email: 'This person is already a member of the team.' };

        expect(mountPage().get('[role="alert"]').text()).toBe('This person is already a member of the team.');
    });

    it('leaves a role change to the member row', async () => {
        const wrapper = mountPage();

        const row = wrapper.findAll('[data-test="member-row"]')[1];
        await row.get('select[name="role"]').setValue('admin');
        await row.get('form').trigger('submit');

        expect(formState.patch).toHaveBeenCalledWith('/teams/7/members/2', expect.any(Object));
    });

    it('leaves the removal and the invitation actions to their rows', async () => {
        const wrapper = mountPage();

        await wrapper.findAll('[data-test="member-row"]')[1].get('[data-test="remove-member"]').trigger('click');
        await wrapper.get('[data-test="resend-invitation"]').trigger('click');
        await wrapper.get('[data-test="cancel-invitation"]').trigger('click');

        expect(formState.delete).toHaveBeenNthCalledWith(1, '/teams/7/members/2', expect.any(Object));
        expect(formState.post).toHaveBeenCalledWith('/teams/7/invitations/30/resend', expect.any(Object));
        expect(formState.delete).toHaveBeenNthCalledWith(2, '/teams/7/invitations/30', expect.any(Object));
    });
});
