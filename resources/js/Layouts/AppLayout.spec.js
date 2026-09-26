import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';
import { mount } from '@vue/test-utils';
import AppLayout from './AppLayout.vue';

const { holder, pageState, LinkStub } = vi.hoisted(() => {
    const pageState = { url: '/', props: { flash: {} } };
    const holder = { page: null };

    const LinkStub = {
        props: ['href'],
        emits: ['click'],
        template: '<a :href="href" @click="$emit(\'click\')"><slot /></a>',
    };

    return { holder, pageState, LinkStub };
});

vi.mock('@inertiajs/vue3', () => ({
    Link: LinkStub,
    usePage: () => {
        holder.page = reactive(pageState);
        return holder.page;
    },
}));

const FlashMessagesStub = {
    props: ['messages'],
    template: '<div data-test="flash"><p v-for="(value, tone) in messages" :key="tone">{{ tone }}:{{ value }}</p></div>',
};

const IconStub = {
    props: ['name'],
    template: '<span data-test="icon" />',
};

const AvatarStub = {
    props: ['name', 'src', 'size'],
    template: '<span data-test="avatar" />',
};

const ThemeToggleStub = {
    props: ['theme'],
    template: '<button type="button" data-test="theme-toggle">{{ theme }}</button>',
};

const global = {
    stubs: {
        FlashMessages: FlashMessagesStub,
        Icon: IconStub,
        Avatar: AvatarStub,
        ThemeToggle: ThemeToggleStub,
    },
};

describe('AppLayout.vue', () => {
    beforeEach(() => {
        document.documentElement.classList.remove('dark');

        if (holder.page) {
            holder.page.url = '/';
            holder.page.props = { flash: {} };
        } else {
            pageState.url = '/';
            pageState.props = { flash: {} };
        }
    });

    it('renders the brand and default navigation items', () => {
        const wrapper = mount(AppLayout, {
            props: { appName: 'TaskFlow' },
            global,
        });

        expect(wrapper.text()).toContain('TaskFlow');
        for (const label of ['Dashboard', 'Projects', 'Tasks', 'Settings']) {
            expect(wrapper.text()).toContain(label);
        }
    });

    it('marks the nav item matching the current url as active', () => {
        pageState.url = '/tasks';
        const wrapper = mount(AppLayout, { global });

        const anchors = wrapper.findAll('nav a');
        const tasks = anchors.find((anchor) => anchor.attributes('href') === '/tasks');
        const dashboard = anchors.find((anchor) => anchor.attributes('href') === '/');

        expect(tasks.attributes('aria-current')).toBe('page');
        expect(dashboard.attributes('aria-current')).toBeUndefined();
    });

    it('renders custom navigation items passed as props', () => {
        const wrapper = mount(AppLayout, {
            props: {
                navItems: [{ label: 'Reports', href: '/reports', icon: 'plus' }],
            },
            global,
        });

        expect(wrapper.text()).toContain('Reports');
        expect(wrapper.text()).not.toContain('Dashboard');
    });

    it('opens and closes the mobile drawer with the menu buttons', async () => {
        const wrapper = mount(AppLayout, { global });
        const aside = wrapper.find('aside');

        expect(aside.classes()).toContain('-translate-x-full');

        await wrapper.find('[aria-label="Open menu"]').trigger('click');
        expect(aside.classes()).toContain('translate-x-0');

        await wrapper.find('[aria-label="Close menu"]').trigger('click');
        expect(aside.classes()).toContain('-translate-x-full');
    });

    it('closes the drawer when the route changes', async () => {
        const wrapper = mount(AppLayout, { global });
        const aside = wrapper.find('aside');

        await wrapper.find('[aria-label="Open menu"]').trigger('click');
        expect(aside.classes()).toContain('translate-x-0');

        holder.page.url = '/projects';
        await nextTick();

        expect(aside.classes()).toContain('-translate-x-full');
    });

    it('closes the drawer when a nav link is clicked', async () => {
        const wrapper = mount(AppLayout, { global });
        const aside = wrapper.find('aside');

        await wrapper.find('[aria-label="Open menu"]').trigger('click');
        expect(aside.classes()).toContain('translate-x-0');

        await wrapper.findAll('nav a')[1].trigger('click');
        expect(aside.classes()).toContain('-translate-x-full');
    });

    it('closes the drawer when the brand link is clicked', async () => {
        const wrapper = mount(AppLayout, { global });
        const aside = wrapper.find('aside');

        await wrapper.find('[aria-label="Open menu"]').trigger('click');
        expect(aside.classes()).toContain('translate-x-0');

        await wrapper.find('aside a').trigger('click');
        expect(aside.classes()).toContain('-translate-x-full');
    });

    it('closes the drawer when the overlay is clicked', async () => {
        const wrapper = mount(AppLayout, { global });
        const aside = wrapper.find('aside');

        await wrapper.find('[aria-label="Open menu"]').trigger('click');
        expect(aside.classes()).toContain('translate-x-0');

        await wrapper.find('[aria-hidden="true"]').trigger('click');
        expect(aside.classes()).toContain('-translate-x-full');
    });

    it('passes flash messages from shared props to FlashMessages', () => {
        pageState.props = { flash: { success: 'Saved.' } };
        const wrapper = mount(AppLayout, { global });

        const flash = wrapper.find('[data-test="flash"]');
        expect(flash.text()).toContain('success:Saved.');
    });

    it('renders the user in the topbar and sidebar when provided', () => {
        const user = { name: 'Jane Doe', email: 'jane@example.com', avatar: null };
        const wrapper = mount(AppLayout, {
            props: { user },
            global,
        });

        expect(wrapper.text()).toContain('Jane Doe');
        expect(wrapper.text()).toContain('jane@example.com');
        expect(wrapper.findAll('[data-test="avatar"]').length).toBe(2);
    });

    it('renders the theme toggle in the header with the shared theme', () => {
        pageState.props = { flash: {}, theme: 'dark' };

        const wrapper = mount(AppLayout, { global });

        expect(wrapper.find('header [data-test="theme-toggle"]').text()).toBe('dark');
    });

    it('applies the dark class on boot from the shared theme', () => {
        pageState.props = { flash: {}, theme: 'dark' };

        mount(AppLayout, { global });

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('drops the dark class when the shared theme is light', () => {
        document.documentElement.classList.add('dark');
        pageState.props = { flash: {}, theme: 'light' };

        mount(AppLayout, { global });

        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('re-applies the dark class when the shared theme changes', async () => {
        pageState.props = { flash: {}, theme: 'light' };
        mount(AppLayout, { global });
        expect(document.documentElement.classList.contains('dark')).toBe(false);

        holder.page.props = { flash: {}, theme: 'dark' };
        await nextTick();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('renders slot content inside the main area', () => {
        const wrapper = mount(AppLayout, {
            global,
            slots: { default: '<h1>Page body</h1>' },
        });

        expect(wrapper.find('main').text()).toContain('Page body');
    });
});