import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { pageProps, post } = vi.hoisted(() => ({
    pageProps: { errors: {}, progress: false },
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<div />',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: { post },
    usePage: () => ({ props: pageProps }),
}));

import Invitation from './Invitation.vue';

const props = {
    team: { id: 4, name: 'Platform' },
    email: 'invitee@example.com',
    role: { value: 'admin', label: 'Admin', tone: 'sky' },
    expiresAt: '2026-10-07T10:00:00+00:00',
    acceptUrl: '/team-invitations/raw-token',
    status: 'valid',
};

const mountPage = (overrides = {}) => mount(Invitation, { props: { ...props, ...overrides } });

beforeEach(() => {
    pageProps.errors = {};
    post.mockReset();
});

describe('Teams/Invitation.vue', () => {
    it('summarises the invitation', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Join Platform');
        expect(wrapper.text()).toContain('invitee@example.com');
        expect(wrapper.text()).toContain('Admin');
        expect(wrapper.text()).toContain('7 October 2026');
    });

    it('posts the acceptance to the invitation endpoint', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="accept-button"]').trigger('click');

        expect(post).toHaveBeenCalledWith('/team-invitations/raw-token', expect.any(Object));
    });

    it('hides the accept button once the invitation is not usable', () => {
        const wrapper = mountPage({ status: 'expired' });

        expect(wrapper.find('[data-test="accept-button"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="invitation-notice"]').text()).toContain('has expired');
    });

    it('explains a revoked invitation', () => {
        const wrapper = mountPage({ status: 'revoked' });

        expect(wrapper.get('[data-test="invitation-notice"]').text()).toContain('revoked by the team');
    });

    it('explains an invitation addressed to somebody else', () => {
        const wrapper = mountPage({ status: 'email_mismatch' });

        expect(wrapper.get('[data-test="invitation-notice"]').text()).toContain('another email address');
    });

    it('shows the rejection returned by the server and blocks a retry', () => {
        pageProps.errors = { token: 'This invitation has expired.' };
        const wrapper = mountPage();

        expect(wrapper.get('[data-test="token-error"]').text()).toBe('This invitation has expired.');
        expect(wrapper.find('[data-test="accept-button"]').exists()).toBe(false);
    });

    it('sends an existing member to the team instead of asking to accept', () => {
        const wrapper = mountPage({ status: 'already_member' });

        expect(wrapper.find('[data-test="accept-button"]').exists()).toBe(false);
        expect(wrapper.get('a[href="/teams/4"]').text()).toBe('Open the team');
    });
});
