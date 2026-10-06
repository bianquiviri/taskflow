import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

/** happy-dom always reports a wide viewport, so the drawer behaviour is driven from here. */
function viewport(width) {
    const matches = width >= 1024;
    const listeners = new Set();

    window.matchMedia = vi.fn((query) => ({
        media: query,
        get matches() {
            return matches;
        },
        addEventListener: (type, listener) => listeners.add(listener),
        removeEventListener: (type, listener) => listeners.delete(listener),
        dispatch: () => listeners.forEach((listener) => listener({ matches })),
    }));
}

function pressKey(key) {
    document.dispatchEvent(new window.KeyboardEvent('keydown', {
        key,
        bubbles: true,
        cancelable: true,
    }));
}

describe('AppLayout.vue', () => {
    beforeEach(() => {
        document.documentElement.classList.remove('dark');
        viewport(1280);

        if (holder.page) {
            holder.page.url = '/';
            holder.page.props = { flash: {} };
        } else {
            pageState.url = '/';
            pageState.props = { flash: {} };
        }
    });

    afterEach(() => {
        document.body.innerHTML = '';
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

    it('points the dashboard navigation item at the dashboard', () => {
        const wrapper = mount(AppLayout, { global });

        const dashboard = wrapper.findAll('nav a').find((anchor) => anchor.text() === 'Dashboard');

        expect(dashboard.attributes('href')).toBe('/dashboard');
    });

    it('points the tasks navigation item at my tasks and settings at the profile', () => {
        const wrapper = mount(AppLayout, { global });

        const anchors = wrapper.findAll('nav a');

        expect(anchors.find((anchor) => anchor.text() === 'Tasks').attributes('href')).toBe('/tasks/mine');
        expect(anchors.find((anchor) => anchor.text() === 'Settings').attributes('href')).toBe('/profile');
    });

    it('marks the nav item matching the current url as active', () => {
        pageState.url = '/tasks/mine';
        const wrapper = mount(AppLayout, { global });

        const anchors = wrapper.findAll('nav a');
        const tasks = anchors.find((anchor) => anchor.attributes('href') === '/tasks/mine');
        const dashboard = anchors.find((anchor) => anchor.attributes('href') === '/dashboard');

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

        await wrapper.find('[data-test="drawer-overlay"]').trigger('click');
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

    it('falls back to the shared auth user when no user prop is given', () => {
        pageState.props = {
            flash: {},
            can: [],
            auth: { user: { id: 7, name: 'Grace Hopper', email: 'grace@example.com' } },
        };

        const wrapper = mount(AppLayout, { global });

        expect(wrapper.text()).toContain('Grace Hopper');
        expect(wrapper.text()).toContain('grace@example.com');
    });

    it('links the current team in the navigation only with the teams.view permission', () => {
        pageState.props = {
            flash: {},
            can: [],
            auth: { team: { id: 3, name: 'Analytical Engines', role: 'owner' } },
        };

        const denied = mount(AppLayout, { global });
        expect(denied.text()).not.toContain('Analytical Engines');

        holder.page.props = {
            flash: {},
            can: ['teams.view'],
            auth: { team: { id: 3, name: 'Analytical Engines', role: 'member' } },
        };

        const granted = mount(AppLayout, { global });
        const team = granted.findAll('nav a').find((anchor) => anchor.attributes('href') === '/teams/3');

        expect(team.text()).toContain('Analytical Engines');
    });

    it('hides the team navigation link when there is no team', () => {
        pageState.props = { flash: {}, can: ['teams.view'], auth: { team: null } };

        const wrapper = mount(AppLayout, { global });

        expect(wrapper.findAll('nav a').map((anchor) => anchor.attributes('href'))).not.toContain('/teams/undefined');
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

    describe('landmarks', () => {
        it('exposes a single main region with a stable id', () => {
            const wrapper = mount(AppLayout, { global });

            expect(wrapper.findAll('main')).toHaveLength(1);
            expect(wrapper.get('main').attributes('id')).toBe('main-content');
        });

        it('offers a skip link as the very first focusable element', () => {
            const wrapper = mount(AppLayout, { global });

            const skip = wrapper.get('a[href="#main-content"]');

            expect(skip.text()).toBe('Skip to content');
            expect(wrapper.element.firstElementChild).toBe(skip.element);
        });

        it('labels the sidebar navigation landmark', () => {
            const wrapper = mount(AppLayout, { global });

            expect(wrapper.get('nav').attributes('aria-label')).toBe('Main');
        });

        it('links the header to the main region for assistive technology', () => {
            const wrapper = mount(AppLayout, { global });

            expect(wrapper.findAll('header')).toHaveLength(1);
        });
    });

    describe('current page', () => {
        it('keeps the parent item current on a nested url', () => {
            pageState.url = '/projects/7';
            const wrapper = mount(AppLayout, { global });

            const anchors = wrapper.findAll('nav a');
            const projects = anchors.find((anchor) => anchor.attributes('href') === '/projects');

            expect(projects.attributes('aria-current')).toBe('page');
        });

        it('ignores the query string when matching the current url', () => {
            pageState.url = '/tasks/mine?status=todo';
            const wrapper = mount(AppLayout, { global });

            const anchors = wrapper.findAll('nav a');
            const tasks = anchors.find((anchor) => anchor.attributes('href') === '/tasks/mine');

            expect(tasks.attributes('aria-current')).toBe('page');
        });

        it('does not mark a sibling as current', () => {
            pageState.url = '/tasks/mine';
            const wrapper = mount(AppLayout, { global });

            const anchors = wrapper.findAll('nav a');
            const projects = anchors.find((anchor) => anchor.attributes('href') === '/projects');

            expect(projects.attributes('aria-current')).toBeUndefined();
        });
    });

    describe('mobile drawer', () => {
        it('reports its state through the trigger button', async () => {
            viewport(420);
            const wrapper = mount(AppLayout, { global });

            const trigger = wrapper.get('[aria-label="Open menu"]');

            expect(trigger.attributes('aria-expanded')).toBe('false');
            expect(trigger.attributes('aria-controls')).toBe('app-drawer');

            await trigger.trigger('click');

            expect(wrapper.get('[aria-label="Open menu"]').attributes('aria-expanded')).toBe('true');
        });

        it('takes the closed drawer out of the tab order on a narrow viewport', async () => {
            viewport(420);
            const wrapper = mount(AppLayout, { global });

            expect(wrapper.get('aside').attributes('inert')).toBe('');

            await wrapper.find('[aria-label="Open menu"]').trigger('click');

            expect(wrapper.get('aside').attributes('inert')).toBeUndefined();
        });

        it('keeps the drawer reachable on a wide viewport even when collapsed', async () => {
            viewport(1280);
            const wrapper = mount(AppLayout, { global });

            await nextTick();

            expect(wrapper.get('aside').attributes('inert')).toBeUndefined();
        });

        it('moves focus into the drawer when it is opened', async () => {
            viewport(420);
            const wrapper = mount(AppLayout, { global, attachTo: document.body });

            await wrapper.find('[aria-label="Open menu"]').trigger('click');
            await nextTick();
            await nextTick();

            expect(document.activeElement).toBe(wrapper.get('nav').element);
        });

        it('closes the drawer with Escape and hands focus back to the trigger', async () => {
            viewport(420);
            const wrapper = mount(AppLayout, { global, attachTo: document.body });

            await wrapper.find('[aria-label="Open menu"]').trigger('click');
            await nextTick();
            await nextTick();

            pressKey('Escape');
            await nextTick();
            await nextTick();

            expect(wrapper.get('aside').attributes('inert')).toBe('');
            expect(document.activeElement).toBe(wrapper.get('[aria-label="Open menu"]').element);

            wrapper.unmount();
        });

        it('leaves Escape alone while the drawer is closed', () => {
            viewport(420);
            const wrapper = mount(AppLayout, { global });

            pressKey('Escape');

            expect(wrapper.get('aside').attributes('inert')).toBe('');
        });
    });

    it('gives the header avatar an accessible name when there is no adjacent text', () => {
        pageState.props = {
            flash: {},
            can: [],
            auth: { user: { id: 7, name: 'Grace Hopper', email: null } },
        };

        const wrapper = mount(AppLayout, { global });

        const AvatarMeaningful = {
            props: ['name', 'src', 'size', 'decorative'],
            template: '<span data-test="avatar" :aria-hidden="decorative ? \'true\' : undefined" :aria-label="decorative ? undefined : name" />',
        };

        const withAvatar = mount(AppLayout, {
            global: { ...global, stubs: { ...global.stubs, Avatar: AvatarMeaningful } },
        });

        const headerAvatar = withAvatar.get('header [data-test="avatar"]');

        expect(headerAvatar.attributes('aria-hidden')).toBeUndefined();
        expect(headerAvatar.attributes('aria-label')).toBe('Grace Hopper');
        expect(wrapper.findAll('aside [data-test="avatar"]').length).toBeGreaterThan(0);
    });
});