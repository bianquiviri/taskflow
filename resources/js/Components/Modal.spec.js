import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import Modal from './Modal.vue';

const IconStub = {
    props: ['name'],
    template: '<span :data-test="name" />',
};

const ModalHarness = defineComponent({
    components: { Modal },
    setup() {
        const isOpen = ref(false);
        return { isOpen };
    },
    template: '<button data-test="open-modal" @click="isOpen = true">Open</button><Modal v-model="isOpen" title="Delete project"><button data-test="confirm">Confirm</button></Modal>',
});

let wrapper;

function mountModal(props = {}, slots = {}) {
    wrapper = mount(Modal, {
        props: {
            modelValue: true,
            title: 'Delete project',
            description: 'This action cannot be undone.',
            ...props,
        },
        slots: {
            default: '<button data-test="first-action">First action</button><button data-test="last-action">Last action</button>',
            ...slots,
        },
        global: {
            stubs: { Icon: IconStub },
        },
        attachTo: document.body,
    });

    return wrapper;
}

function pressKey(key, shiftKey = false) {
    document.dispatchEvent(new window.KeyboardEvent('keydown', {
        key,
        shiftKey,
        bubbles: true,
        cancelable: true,
    }));
}

describe('Modal.vue', () => {
    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
        document.body.innerHTML = '';
    });

    it('renders an accessible dialog only when open', async () => {
        mountModal();
        await nextTick();

        const dialog = document.querySelector('[role="dialog"]');
        const title = document.querySelector('h2');

        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(dialog.getAttribute('aria-labelledby')).toBe(title.id);
        expect(dialog.getAttribute('aria-describedby')).toBe(
            document.querySelector('[data-test="modal-description"]').id,
        );
    });

    it('emits close events for the close button, Escape, and the overlay', async () => {
        const mounted = mountModal();
        await nextTick();

        document.querySelector('[aria-label="Close dialog"]').click();
        pressKey('Escape');
        document.querySelector('[data-test="modal-overlay"]').click();

        expect(mounted.emitted('close')).toHaveLength(3);
        expect(mounted.emitted('update:modelValue')).toEqual([[false], [false], [false]]);
    });

    it('supports v-model for opening and closing', async () => {
        wrapper = mount(ModalHarness, { attachTo: document.body });

        await wrapper.get('[data-test="open-modal"]').trigger('click');
        await nextTick();
        expect(document.querySelector('[role="dialog"]')).not.toBeNull();

        document.querySelector('[data-test="modal-overlay"]').click();
        await nextTick();
        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });

    it('does not close when the dialog content is clicked', async () => {
        const mounted = mountModal();
        await nextTick();

        document.querySelector('[role="dialog"]').click();

        expect(mounted.emitted('close')).toBeUndefined();
    });

    it('moves focus into the dialog and traps Tab navigation', async () => {
        mountModal();
        await nextTick();

        const closeButton = document.querySelector('[aria-label="Close dialog"]');
        const lastAction = document.querySelector('[data-test="last-action"]');
        const outsideButton = document.createElement('button');
        document.body.appendChild(outsideButton);

        expect(document.activeElement).toBe(closeButton);

        outsideButton.focus();
        pressKey('Tab');
        expect(document.activeElement).toBe(closeButton);

        lastAction.focus();
        pressKey('Tab');
        expect(document.activeElement).toBe(closeButton);

        pressKey('Tab', true);
        expect(document.activeElement).toBe(lastAction);
    });

    it('restores focus to the previously focused element when closed', async () => {
        const opener = document.createElement('button');
        document.body.appendChild(opener);
        opener.focus();

        const mounted = mountModal();
        await nextTick();
        expect(document.activeElement).not.toBe(opener);

        await mounted.setProps({ modelValue: false });
        await nextTick();

        expect(document.activeElement).toBe(opener);
    });

    it('renders default and footer slots', async () => {
        mountModal({}, {
            footer: '<button data-test="footer-slot">Confirm</button>',
        });
        await nextTick();

        expect(document.querySelector('[data-test="last-action"]')).not.toBeNull();
        expect(document.querySelector('[data-test="footer-slot"]')).not.toBeNull();
    });
});
