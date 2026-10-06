import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';

const { forms } = vi.hoisted(() => ({ forms: {} }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (initial) => {
        const form = reactive({
            ...initial,
            processing: false,
            errors: {},
            post: vi.fn(),
            reset: vi.fn(),
        });

        forms[Object.keys(initial).join(',')] = form;

        return form;
    },
}));

import Create from './Create.vue';

const mountPage = () => mount(Create);
const form = () => forms.name;

describe('Pages/Teams/Create.vue', () => {
    beforeEach(() => {
        Object.keys(forms).forEach((key) => delete forms[key]);
    });

    it('asks for the team name', () => {
        const wrapper = mountPage();

        expect(wrapper.find('input[name="name"]').element.value).toBe('');
        expect(wrapper.text()).toContain('Create your team');
    });

    it('posts the new team to the teams route', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-test="create-team-form"]').trigger('submit');

        expect(form().post).toHaveBeenCalledWith('/teams', { preserveScroll: true });
    });

    it('shows the validation errors', async () => {
        const wrapper = mountPage();

        form().errors = { name: 'The name field is required.' };

        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('The name field is required.');
    });

    it('disables the field while the request is processing', async () => {
        const wrapper = mountPage();

        form().processing = true;

        await wrapper.vm.$nextTick();

        expect(wrapper.find('input[name="name"]').element.disabled).toBe(true);
    });

    it('offers a way back to the dashboard', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="cancel-create-team"]').attributes('href')).toBe('/dashboard');
    });
});
