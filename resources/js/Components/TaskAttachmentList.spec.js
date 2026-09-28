import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

const { router } = vi.hoisted(() => ({
    router: { delete: vi.fn() },
}));

vi.mock('@inertiajs/vue3', () => ({ router }));

import TaskAttachmentList from './TaskAttachmentList.vue';

const attachment = (overrides = {}) => ({
    id: 7,
    name: 'spec.pdf',
    mime_type: 'application/pdf',
    size: 2048,
    human_size: '2 KB',
    uploaded_by: 'Ada Lovelace',
    uploaded_at: '2026-09-28T10:00:00+00:00',
    download_url: '/tasks/3/files/7/download',
    delete_url: '/tasks/3/files/7',
    can_delete: true,
    ...overrides,
});

beforeEach(() => {
    router.delete.mockReset();
});

describe('TaskAttachmentList.vue', () => {
    it('invites the first attachment when a task carries none', () => {
        const wrapper = mount(TaskAttachmentList, { props: { attachments: [] } });

        expect(wrapper.text()).toContain('No attachments yet');
        expect(wrapper.findAll('[data-test="attachment"]')).toHaveLength(0);
    });

    it('links every attachment to its authorised download endpoint', () => {
        const wrapper = mount(TaskAttachmentList, {
            props: { attachments: [attachment(), attachment({ id: 8, name: 'notes.txt' })] },
        });

        const links = wrapper.findAll('[data-test="attachment-download"]');

        expect(links).toHaveLength(2);
        expect(links[0].text()).toBe('spec.pdf');
        expect(links[0].attributes('href')).toBe('/tasks/3/files/7/download');
        expect(links[1].attributes('href')).toBe('/tasks/3/files/7/download');
    });

    it('describes the size, the uploader and the moment of the upload', () => {
        const wrapper = mount(TaskAttachmentList, { props: { attachments: [attachment()] } });

        const meta = wrapper.get('[data-test="attachment-meta"]').text();

        expect(meta).toContain('2 KB');
        expect(meta).toContain('Ada Lovelace');
    });

    it('keeps the description readable when a detail is missing', () => {
        const wrapper = mount(TaskAttachmentList, {
            props: { attachments: [attachment({ human_size: null, uploaded_at: null })] },
        });

        expect(wrapper.get('[data-test="attachment-meta"]').text()).toBe('Ada Lovelace');
    });

    it('offers the removal only to a viewer allowed to remove the file', () => {
        const wrapper = mount(TaskAttachmentList, {
            props: { attachments: [attachment({ can_delete: false })] },
        });

        expect(wrapper.find('[data-test="attachment-remove"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('spec.pdf');
    });

    it('removes the file in place, without leaving the page', async () => {
        const wrapper = mount(TaskAttachmentList, { props: { attachments: [attachment()] } });

        await wrapper.get('[data-test="attachment-remove"]').trigger('click');

        expect(router.delete).toHaveBeenCalledWith('/tasks/3/files/7', expect.objectContaining({
            preserveScroll: true,
        }));
    });

    it('releases the button once the removal is finished', async () => {
        const wrapper = mount(TaskAttachmentList, { props: { attachments: [attachment()] } });

        await wrapper.get('[data-test="attachment-remove"]').trigger('click');

        expect(wrapper.get('[data-test="attachment-remove"]').attributes('aria-busy')).toBe('true');

        const [, options] = router.delete.mock.calls[0];
        options.onFinish();
        await nextTick();

        expect(wrapper.get('[data-test="attachment-remove"]').attributes('aria-busy')).toBeUndefined();
    });
});
