import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { events, formState, pageState, router } = vi.hoisted(() => {
    const events = {};
    const stop = vi.fn();

    return {
        events,
        formState: {
            name: '',
            description: '',
            processing: false,
            errors: {},
            transformed: null,
            post: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
            transform: vi.fn(),
        },
        pageState: { url: '/projects', props: { can: ['projects.viewAny', 'projects.create'] } },
        router: {
            on: vi.fn((name, handler) => {
                events[name] = handler;

                return stop;
            }),
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div data-test="head" :data-title="title" />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router,
    usePage: () => pageState,
    useForm: (initial) => {
        Object.assign(formState, initial);

        return formState;
    },
}));

import Index from './Index.vue';
import PageSkeleton from '../../Components/PageSkeleton.vue';
import ProjectFormModal from '../../Components/ProjectFormModal.vue';

const projects = [
    { id: 1, name: 'Website', slug: 'website', description: 'Rebuild the marketing site.' },
    { id: 2, name: 'Mobile app', slug: 'mobile-app', description: null },
];

function mountPage(props = {}) {
    return mount(Index, {
        props: { projects, ...props },
        attachTo: document.body,
    });
}

beforeEach(() => {
    Object.assign(formState, { name: '', description: '', processing: false, errors: {}, transformed: null });
    formState.post.mockReset();
    pageState.props.can = ['projects.viewAny', 'projects.create'];
});

describe('Projects/Index.vue', () => {
    it('lists every project with its description', () => {
        const wrapper = mountPage();
        const rows = wrapper.findAll('[data-test="project-row"]');

        expect(wrapper.get('h1').text()).toBe('Projects');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Website');
        expect(rows[0].text()).toContain('Rebuild the marketing site.');
        expect(rows[1].find('[data-test="project-description"]').exists()).toBe(false);
    });

    it('paints a skeleton while the listing is on its way', async () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(PageSkeleton).exists()).toBe(false);

        events.start({ method: 'get', only: [] });
        await nextTick();

        expect(wrapper.findComponent(PageSkeleton).exists()).toBe(true);
        expect(wrapper.find('[data-test="project-row"]').exists()).toBe(false);

        events.finish({ method: 'get', only: [] });
        await nextTick();

        expect(wrapper.findComponent(PageSkeleton).exists()).toBe(false);
        expect(wrapper.findAll('[data-test="project-row"]')).toHaveLength(2);
    });

    it('explains the empty listing instead of leaving whitespace', () => {
        const wrapper = mountPage({ projects: [] });

        expect(wrapper.get('[data-test="projects-empty"]').text()).toContain('No projects yet');
        expect(wrapper.find('[data-test="project-row"]').exists()).toBe(false);
    });

    it('opens the create dialog from the empty state', async () => {
        const wrapper = mountPage({ projects: [] });
        const modal = wrapper.getComponent(ProjectFormModal);

        expect(modal.props('modelValue')).toBe(false);

        await wrapper.get('[data-test="project-create-empty"]').trigger('click');

        expect(modal.props('modelValue')).toBe(true);
    });

    it('keeps one create trigger in the header once projects exist', async () => {
        const wrapper = mountPage();
        const modal = wrapper.getComponent(ProjectFormModal);

        expect(wrapper.find('[data-test="project-create-empty"]').exists()).toBe(false);

        await wrapper.get('[data-test="project-create"]').trigger('click');

        expect(modal.props('modelValue')).toBe(true);
    });

    it('creates the project through the existing route', async () => {
        const wrapper = mountPage({ projects: [] });

        await wrapper.get('[data-test="project-create-empty"]').trigger('click');

        formState.name = 'Website';
        formState.description = 'Rebuild the marketing site.';
        document.querySelector('[role="dialog"] form').dispatchEvent(new window.Event('submit'));
        await nextTick();

        expect(formState.post).toHaveBeenCalledWith('/projects', expect.any(Object));
    });

    it('hides the create dialog from a user without the permission', () => {
        pageState.props.can = ['projects.viewAny'];

        const wrapper = mountPage({ projects: [] });

        expect(wrapper.find('[data-test="project-create"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="projects-empty"]').text()).toContain('No projects yet');
    });

    it('sets the page title', () => {
        expect(mountPage().get('[data-test="head"]').attributes('data-title')).toBe('Projects');
    });
});
