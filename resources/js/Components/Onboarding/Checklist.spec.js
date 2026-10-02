import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Checklist from './Checklist.vue';

const mountChecklist = (props = {}) => mount(Checklist, { props });

const steps = (wrapper) => wrapper.findAll('[data-test="onboarding-step"]');
const step = (wrapper, key) => wrapper.get(`[data-step="${key}"]`);
const cta = (wrapper, key) => step(wrapper, key).find('[data-test="onboarding-step-cta"]');

describe('Onboarding/Checklist.vue', () => {
    it('walks through the four steps in order', () => {
        const wrapper = mountChecklist();

        expect(steps(wrapper).map((row) => row.attributes('data-step')))
            .toEqual(['team', 'invite', 'project', 'task']);
        expect(steps(wrapper).map((row) => row.get('[data-test="onboarding-step-title"]').text()))
            .toEqual([
                'Create your team',
                'Invite a teammate',
                'Create your first project',
                'Add your first task',
            ]);
    });

    it('counts the steps already taken', () => {
        const wrapper = mountChecklist({ hasTeam: true, hasProject: true, invited: true });

        expect(wrapper.get('[data-test="onboarding-progress"]').text()).toBe('3 of 4 steps done');
        expect(step(wrapper, 'team').attributes('data-done')).toBe('true');
        expect(step(wrapper, 'project').attributes('data-done')).toBe('true');
        expect(step(wrapper, 'invite').attributes('data-done')).toBe('true');
        expect(step(wrapper, 'task').attributes('data-done')).toBe('false');
    });

    it('states the finished steps for a screen reader, numbers not at all', () => {
        const wrapper = mountChecklist({ hasTeam: true });

        expect(step(wrapper, 'team').get('[data-test="onboarding-step-done"]').text()).toBe('Done');
        expect(step(wrapper, 'project').get('[data-test="onboarding-step-pending"] span').attributes('aria-hidden'))
            .toBe('true');
    });

    it('celebrates a finished checklist instead of asking for more', () => {
        const wrapper = mountChecklist({ hasTeam: true, hasProject: true, hasTask: true, invited: true });

        expect(wrapper.get('[data-test="onboarding-done"]').text()).toBe('Everything is set up.');
        expect(steps(wrapper)).toHaveLength(0);
    });

    it('sends the team and invitation steps to the team page', () => {
        const wrapper = mountChecklist({ hasTeam: true, teamId: 4 });

        expect(cta(wrapper, 'team').attributes('href')).toBe('/teams/4');
        expect(cta(wrapper, 'team').text()).toBe('Open your team');
        expect(cta(wrapper, 'invite').attributes('href')).toBe('/teams/4');
        expect(cta(wrapper, 'invite').text()).toBe('Invite somebody');
    });

    it('says the team cannot be created from the app yet', () => {
        const wrapper = mountChecklist();

        expect(step(wrapper, 'team').get('[data-test="onboarding-step-unavailable"]').text())
            .toContain('not available in the app yet');
        expect(cta(wrapper, 'team').exists()).toBe(false);
    });

    it('sends the project step to the projects listing, the only create flow there is', () => {
        const wrapper = mountChecklist();

        expect(cta(wrapper, 'project').attributes('href')).toBe('/projects');
        expect(cta(wrapper, 'project').text()).toBe('Go to projects');
    });

    it('sends the first task to the board that creates tasks', () => {
        const wrapper = mountChecklist({ projectId: 7 });

        expect(cta(wrapper, 'task').attributes('href')).toBe('/projects/7');
        expect(cta(wrapper, 'task').text()).toBe('Open the board');
    });

    it('falls back to the projects listing when there is no project to open', () => {
        const wrapper = mountChecklist();

        expect(cta(wrapper, 'task').attributes('href')).toBe('/projects');
        expect(cta(wrapper, 'task').text()).toBe('Go to projects');
    });
});